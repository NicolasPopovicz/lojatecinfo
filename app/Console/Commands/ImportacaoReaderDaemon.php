<?php

namespace App\Console\Commands;

use App\Enums\StatusImportacao;
use App\Models\Importacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use SplFileObject;
use Throwable;

class ImportacaoReaderDaemon extends Command
{
    protected $signature   = 'importacao:reader';
    protected $description = 'Daemon que lê arquivos CSV e distribui chunks para os workers';

    private const LINHAS_POR_CHUNK  = 10_000;
    private const TTL_CHUNK         = 7_200;   // 2 horas
    public  const FILA_IMPORTACAO   = 'importacao:fila';
    public  const FILA_WORK         = 'importacao:work';
    private const COLUNAS_ESPERADAS = ['descricao', 'nomecliente', 'produto', 'preco', 'quantidade'];

    private string $tag;

    public function handle(): void
    {
        $this->tag = '[reader:' . getmypid() . ']';

        Log::info("{$this->tag} Daemon iniciado.");

        while (true) {
            $resultado = Redis::blpop(self::FILA_IMPORTACAO, 2);

            if (!$resultado) {
                continue;
            }

            [, $importacaoId] = $resultado;
            $importacaoId = (int) $importacaoId;

            try {
                $this->processarArquivo($importacaoId);
            } catch (Throwable $e) {
                Log::error("{$this->tag} Falha na importação #{$importacaoId}: {$e->getMessage()}");
                Importacao::where('id', $importacaoId)->update([
                    'status'       => StatusImportacao::Falhou,
                    'concluido_em' => now(),
                ]);
            }
        }
    }

    private function processarArquivo(int $importacaoId): void
    {
        $importacao = Importacao::findOrFail($importacaoId);

        Log::info("{$this->tag} #{$importacaoId} — iniciando leitura de '{$importacao->arquivo_original}'.");

        $importacao->update([
            'status'      => StatusImportacao::Lendo,
            'iniciado_em' => now(),
        ]);

        $arquivo = new SplFileObject(storage_path($importacao->caminho));
        $arquivo->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $arquivo->setCsvControl(';', '"', '');

        $cabecalho = array_map(fn(string $c) => mb_strtolower(trim($c)), $arquivo->current() ?: []);
        $faltando  = array_diff(self::COLUNAS_ESPERADAS, $cabecalho);

        if (!empty($faltando)) {
            $this->escreverResumoFatal($importacaoId, [[
                'parametro' => 'cabecalho',
                'linhas'    => 1,
                'erros'     => [['descricao' => 'Colunas ausentes: ' . implode(', ', $faltando), 'exemplo' => '', 'total' => 1]],
            ]]);
            $importacao->update([
                'status'       => StatusImportacao::Falhou,
                'concluido_em' => now(),
                'erros_resumo' => "importacoes/erros/{$importacaoId}/resumo.json",
            ]);
            return;
        }

        $mapa         = array_flip($cabecalho);
        $arquivo->next();

        $chunkAtual   = [];
        $numeroChunk  = 0;
        $totalLinhas  = 0;
        $linhasVazias = 0;

        while (!$arquivo->eof()) {
            $linha = $arquivo->current();
            $arquivo->next();

            if ($linha === false || $linha === [null] || empty(array_filter($linha, fn($v) => $v !== ''))) {
                // Ignora o \n final do arquivo — after next(), eof() já é true
                if (!$arquivo->eof()) {
                    $linhasVazias++;
                }
                continue;
            }

            $totalLinhas++;
            $chunkAtual[] = [
                'descricao'   => trim($linha[$mapa['descricao']]   ?? ''),
                'nomecliente' => trim($linha[$mapa['nomecliente']]  ?? ''),
                'produto'     => trim($linha[$mapa['produto']]      ?? ''),
                'preco'       => trim($linha[$mapa['preco']]        ?? ''),
                'quantidade'  => trim($linha[$mapa['quantidade']]   ?? ''),
            ];

            if (count($chunkAtual) >= self::LINHAS_POR_CHUNK) {
                $this->armazenarEEnfileirar($importacaoId, $numeroChunk, $chunkAtual);
                Importacao::where('id', $importacaoId)->increment('total_linhas', self::LINHAS_POR_CHUNK);
                Log::info("{$this->tag} #{$importacaoId} — chunk #{$numeroChunk} enfileirado ({$totalLinhas} linhas lidas).");
                $chunkAtual = [];
                $numeroChunk++;
            }
        }

        if (!empty($chunkAtual)) {
            $this->armazenarEEnfileirar($importacaoId, $numeroChunk, $chunkAtual);
            Importacao::where('id', $importacaoId)->increment('total_linhas', count($chunkAtual));
            Log::info("{$this->tag} #{$importacaoId} — chunk final #{$numeroChunk} enfileirado.");
        }

        if ($linhasVazias > 0) {
            Importacao::where('id', $importacaoId)->increment('total_linhas', $linhasVazias);

            Redis::rpush(ImportacaoSpoolDaemon::FILA_SPOOL, json_encode([
                'importacao_id' => $importacaoId,
                'arquivo'       => null,
                'total_chunk'   => $linhasVazias,
                'validas'       => 0,
                'erros'         => $linhasVazias,
            ]));

            $this->escreverReaderJson($importacaoId, $linhasVazias);

            Log::info("{$this->tag} #{$importacaoId} — {$linhasVazias} linhas vazias contabilizadas.");
        }

        if ($totalLinhas === 0 && $linhasVazias === 0) {
            $this->escreverResumoFatal($importacaoId, [[
                'parametro' => 'arquivo',
                'linhas'    => 1,
                'erros'     => [['descricao' => 'O arquivo não contém registros.', 'exemplo' => '', 'total' => 1]],
            ]]);
            $importacao->update([
                'status'       => StatusImportacao::Falhou,
                'concluido_em' => now(),
                'erros_resumo' => "importacoes/erros/{$importacaoId}/resumo.json",
            ]);
            return;
        }

        $importacao->update(['status' => StatusImportacao::Processando]);

        Log::info("{$this->tag} #{$importacaoId} — {$totalLinhas} linhas em " . ($numeroChunk + 1) . " chunks. Leitura concluída.");

        Redis::rpush(ImportacaoSpoolDaemon::FILA_SPOOL, json_encode([
            'importacao_id'       => $importacaoId,
            'arquivo'             => null,
            'total_chunk'         => 0,
            'validas'             => 0,
            'erros'               => 0,
            'verificar_conclusao' => true,
        ]));
    }

    private function armazenarEEnfileirar(int $importacaoId, int $numero, array $linhas): void
    {
        $chave = "importacao:{$importacaoId}:lote:{$numero}";

        Redis::setex($chave, self::TTL_CHUNK, json_encode($linhas, JSON_UNESCAPED_UNICODE));
        Redis::rpush(self::FILA_WORK, "{$importacaoId}:{$numero}");
    }

    /**
     * Grava erros pré-agregados do Reader (linhas vazias) em arquivo JSON.
     * O SpoolDaemon vai ler este arquivo na conclusão e fundir com os erros dos Workers.
     */
    private function escreverReaderJson(int $importacaoId, int $linhasVazias): void
    {
        $dir = storage_path("importacoes/erros/{$importacaoId}");
        @mkdir($dir, 0755, true);

        file_put_contents(
            "{$dir}/_reader.json",
            json_encode([
                [
                    'parametro' => 'linha',
                    'linhas'    => $linhasVazias,
                    'erros'     => [
                        ['descricao' => 'Linha vazia ou sem dados.', 'exemplo' => '', 'total' => $linhasVazias],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Grava resumo de erro fatal diretamente em resumo.json (usado quando a importação
     * falha antes mesmo de começar a processar, ex: cabeçalho inválido ou arquivo vazio).
     */
    private function escreverResumoFatal(int $importacaoId, array $erros): void
    {
        $dir = storage_path("importacoes/erros/{$importacaoId}");
        @mkdir($dir, 0755, true);

        // $erros já vem no formato [{parametro, linhas, erros:[...]}] do caller
        file_put_contents("{$dir}/resumo.json", json_encode($erros, JSON_UNESCAPED_UNICODE));
    }
}
