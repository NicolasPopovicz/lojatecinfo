<?php

namespace App\Console\Commands;

use App\Enums\StatusImportacao;
use App\Models\Importacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use SplFileObject;
use Throwable;

class ImportacaoReaderDaemon extends Command
{
    protected $signature   = 'importacao:reader';
    protected $description = 'Daemon que lê arquivos CSV e distribui chunks para os workers';

    private const LINHAS_POR_CHUNK  = 5_000;
    private const TTL_CHUNK         = 7_200;    // 2 horas
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

        $arquivo = new SplFileObject(Storage::path($importacao->caminho));
        $arquivo->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::DROP_NEW_LINE
        );
        $arquivo->setCsvControl(';', '"', '');

        $cabecalho = array_map(fn(string $c) => mb_strtolower(trim($c)), $arquivo->current() ?: []);
        $faltando  = array_diff(self::COLUNAS_ESPERADAS, $cabecalho);

        if (!empty($faltando)) {
            $importacao->update([
                'status'        => StatusImportacao::Falhou,
                'concluido_em'  => now(),
                'amostra_erros' => [['linha' => 1, 'erros' => ['Colunas ausentes: ' . implode(', ', $faltando)]]],
            ]);
            return;
        }

        $mapa        = array_flip($cabecalho);
        $arquivo->next();

        $chunkAtual  = [];
        $numeroChunk = 0;
        $totalLinhas = 0;

        while (!$arquivo->eof()) {
            $linha = $arquivo->current();
            $arquivo->next();

            if ($linha === false || $linha === [null] || empty(array_filter($linha, fn($v) => $v !== ''))) {
                continue;
            }

            $chunkAtual[] = [
                'descricao'   => trim($linha[$mapa['descricao']]   ?? ''),
                'nomecliente' => trim($linha[$mapa['nomecliente']]  ?? ''),
                'produto'     => trim($linha[$mapa['produto']]      ?? ''),
                'preco'       => trim($linha[$mapa['preco']]        ?? ''),
                'quantidade'  => trim($linha[$mapa['quantidade']]   ?? ''),
            ];
            $totalLinhas++;

            if (count($chunkAtual) >= self::LINHAS_POR_CHUNK) {
                $this->armazenarEEnfileirar($importacaoId, $numeroChunk, $chunkAtual);
                Importacao::where('id', $importacaoId)->increment('total_linhas', count($chunkAtual));
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

        if ($totalLinhas === 0) {
            $importacao->update([
                'status'        => StatusImportacao::Falhou,
                'concluido_em'  => now(),
                'amostra_erros' => [['linha' => 1, 'erros' => ['O arquivo não contém registros.']]],
            ]);
            return;
        }

        // Muda para Processando — a partir daqui o spool daemon pode concluir.
        // O completion check usa WHERE status = 'processando', então enquanto
        // o status era 'lendo' não havia risco de conclusão prematura.
        $importacao->update(['status' => StatusImportacao::Processando]);

        $totalChunks = $numeroChunk + 1;
        Log::info("{$this->tag} #{$importacaoId} — {$totalLinhas} linhas em {$totalChunks} chunks. Leitura concluída, processando.");

        // Sentinel: garante que verificarConclusao seja chamado após a transição
        // para Processando, mesmo que todos os chunks já tenham sido carregados.
        Redis::rpush(ImportacaoSpoolDaemon::FILA_SPOOL, json_encode([
            'importacao_id'       => $importacaoId,
            'arquivo'             => null,
            'total_chunk'         => 0,
            'validas'             => 0,
            'erros'               => 0,
            'amostras_erro'       => [],
            'verificar_conclusao' => true,
        ]));
    }

    /**
     * Armazena o chunk no Redis e enfileira imediatamente para os workers.
     * Ao enfileirar chunk a chunk (em vez de em lote no final), múltiplos
     * readers em paralelo intercalam seus chunks na fila, garantindo que
     * dois arquivos sejam processados simultaneamente.
     */
    private function armazenarEEnfileirar(int $importacaoId, int $numero, array $linhas): void
    {
        $chave = "importacao:{$importacaoId}:lote:{$numero}";

        Redis::setex($chave, self::TTL_CHUNK, json_encode($linhas, JSON_UNESCAPED_UNICODE));
        Redis::rpush(self::FILA_WORK, "{$importacaoId}:{$numero}");
    }
}
