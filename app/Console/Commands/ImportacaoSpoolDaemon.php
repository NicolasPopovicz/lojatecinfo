<?php

namespace App\Console\Commands;

use App\Enums\StatusImportacao;
use App\Models\Importacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ImportacaoSpoolDaemon extends Command
{
    protected $signature   = 'importacao:spool';
    protected $description = 'Daemon que carrega spool CSV no Postgres via COPY';

    public const FILA_SPOOL = 'importacao:spool';

    // Colunas na mesma ordem que o worker escreve no CSV
    private const COLUNAS_COPY = 'descricao, nomecliente, produto, preco, quantidade, total, created_at, updated_at';

    private string $tag;

    public function handle(): void
    {
        $this->tag = '[spool:' . getmypid() . ']';

        Log::info("{$this->tag} Daemon iniciado.");

        while (true) {
            $resultado = Redis::blpop(self::FILA_SPOOL, 2);

            if (!$resultado) {
                continue;
            }

            [, $json] = $resultado;
            $meta = json_decode($json, associative: true);

            try {
                $this->processarSpool($meta);
            } catch (Throwable $e) {
                Log::error("{$this->tag} Falha no spool da importação #{$meta['importacao_id']}: {$e->getMessage()}");
                $this->contabilizarFalhaSpool($meta);
            }
        }
    }

    private function processarSpool(array $meta): void
    {
        $importacaoId = (int) $meta['importacao_id'];
        $arquivo      = $meta['arquivo'];
        $totalChunk   = (int) $meta['total_chunk'];
        $qtdValidas   = (int) $meta['validas'];
        $qtdErros     = (int) $meta['erros'];

        // Sentinel do reader: total_linhas já foi gravado, só verifica conclusão
        if (!empty($meta['verificar_conclusao'])) {
            $this->verificarConclusao($importacaoId);
            return;
        }

        // Importação removida (cancelada e deletada) — descarta silenciosamente
        $status = Importacao::where('id', $importacaoId)->value('status');

        if ($status === null || $status === StatusImportacao::Cancelada->value) {
            if ($arquivo) {
                @unlink($arquivo);
            }
            return;
        }

        // Carrega no Postgres via COPY — 10-100x mais rápido que INSERT em lote
        if ($qtdValidas > 0) {
            if (!$arquivo || !file_exists($arquivo)) {
                Log::warning("{$this->tag} Arquivo spool não encontrado: {$arquivo}. Chunk contabilizado como erros.");
                $qtdErros  += $qtdValidas;
                $qtdValidas = 0;
            } else {
                $this->executarCopy($importacaoId, $arquivo, $meta);
            }
        }

        if ($arquivo) {
            @unlink($arquivo);
            $this->removerDiretorioSeVazio(dirname($arquivo));
        }

        DB::table('importacoes')
            ->where('id', $importacaoId)
            ->update([
                'linhas_processadas' => DB::raw("linhas_processadas + {$totalChunk}"),
                'linhas_com_erro'    => DB::raw("linhas_com_erro + {$qtdErros}"),
            ]);

        Log::info("{$this->tag} #{$importacaoId} chunk ok — inseridas: {$qtdValidas} | erros: {$qtdErros}");

        $this->verificarConclusao($importacaoId);
    }

    private function executarCopy(int $importacaoId, string $arquivo, array $meta): void
    {
        try {
            $arquivoEscapado = str_replace("'", "''", $arquivo);
            DB::statement("COPY pedidos (" . self::COLUNAS_COPY . ") FROM '{$arquivoEscapado}' WITH (FORMAT csv)");
        } catch (Throwable $e) {
            $this->gerarArquivosDebug($importacaoId, $arquivo, $meta, $e->getMessage());
            throw $e;
        }
    }

    private function gerarArquivosDebug(int $importacaoId, string $csvOriginal, array $meta, string $erro): void
    {
        $dir = storage_path('importacoes/debug');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $ts   = now()->format('Ymd_His');
        $base = "{$dir}/{$importacaoId}_chunk{$meta['total_chunk']}_{$ts}";

        @copy($csvOriginal, "{$base}.csv");

        $sql = implode("\n", [
            "-- Importação #{$importacaoId} — falha em " . now()->toDateTimeString(),
            "-- Erro: {$erro}",
            "-- Linhas no chunk: {$meta['total_chunk']} | válidas: {$meta['validas']} | erros de validação: {$meta['erros']}",
            "--",
            "COPY pedidos (" . self::COLUNAS_COPY . ")",
            "FROM '{$base}.csv'",
            "WITH (FORMAT csv);",
        ]);

        file_put_contents("{$base}.sql", $sql);

        Log::error("{$this->tag} Arquivos de debug gravados: {$base}.csv e {$base}.sql");
    }

    private function verificarConclusao(int $importacaoId): void
    {
        $atualizado = DB::table('importacoes')
            ->where('id', $importacaoId)
            ->where('status', StatusImportacao::Processando->value)
            ->where('total_linhas', '>', 0)
            ->whereColumn('linhas_processadas', '>=', 'total_linhas')
            ->update([
                'status'       => StatusImportacao::Concluido->value,
                'concluido_em' => now(),
            ]);

        if ($atualizado) {
            $this->agregarErrosDosDiscos($importacaoId);

            $importacao = Importacao::find($importacaoId);
            $inseridas  = $importacao->linhas_processadas - $importacao->linhas_com_erro;
            Log::info(
                "{$this->tag} Importação #{$importacaoId} CONCLUÍDA. " .
                "Total: {$importacao->total_linhas} | " .
                "Inseridas: {$inseridas} | " .
                "Erros: {$importacao->linhas_com_erro}"
            );
        }
    }

    /**
     * Lê _reader.json (erros pré-agregados do Reader) e todos os JSONL dos Workers,
     * grava o resumo final em resumo.json e salva só o caminho no banco.
     * Executado uma única vez na conclusão — zero overhead durante o processamento.
     */
    private function agregarErrosDosDiscos(int $importacaoId): void
    {
        $dir = storage_path("importacoes/erros/{$importacaoId}");

        // Mapa interno: parametro → ['linhas' => N, 'erros' => [mensagem => [descricao, exemplo, total]]]
        $merged = [];

        // Erros pré-agregados do Reader (_reader.json já vem no formato final aninhado)
        $readerFile = "{$dir}/_reader.json";
        if (file_exists($readerFile)) {
            $items = json_decode(file_get_contents($readerFile), true) ?? [];
            foreach ($items as $item) {
                $p = $item['parametro'];
                $merged[$p] = ['linhas' => $item['linhas'], 'erros' => []];
                foreach ($item['erros'] as $erro) {
                    $merged[$p]['erros'][$erro['descricao']] = $erro;
                }
            }
        }

        // Erros individuais dos Workers (JSONL): agrupa por parametro → mensagem
        $arquivos = is_dir($dir) ? (glob("{$dir}/*.jsonl") ?: []) : [];
        foreach ($arquivos as $arquivo) {
            $fh = fopen($arquivo, 'r');
            while (($linha = fgets($fh)) !== false) {
                $item = json_decode(trim($linha), true);
                if (!$item) {
                    continue;
                }
                foreach ($item['erros'] as $campo => $msgs) {
                    $mensagem = is_array($msgs) ? implode(', ', $msgs) : (string) $msgs;
                    $exemplo  = $item['dados'][$campo] ?? '';

                    if (!isset($merged[$campo])) {
                        $merged[$campo] = ['linhas' => 0, 'erros' => []];
                    }

                    $merged[$campo]['linhas']++;

                    if (isset($merged[$campo]['erros'][$mensagem])) {
                        $merged[$campo]['erros'][$mensagem]['total']++;
                    } else {
                        $merged[$campo]['erros'][$mensagem] = [
                            'descricao' => $mensagem,
                            'exemplo'   => $exemplo,
                            'total'     => 1,
                        ];
                    }
                }
            }
            fclose($fh);
        }

        if (empty($merged)) {
            return;
        }

        // Converte para array de objetos no formato final
        $resultado = [];
        foreach ($merged as $parametro => $info) {
            $resultado[] = [
                'parametro' => $parametro,
                'linhas'    => $info['linhas'],
                'erros'     => array_values($info['erros']),
            ];
        }

        $resumoPath = "{$dir}/resumo.json";
        file_put_contents($resumoPath, json_encode($resultado, JSON_UNESCAPED_UNICODE));

        DB::table('importacoes')
            ->where('id', $importacaoId)
            ->update(['erros_resumo' => "importacoes/erros/{$importacaoId}/resumo.json"]);

        Log::info("{$this->tag} #{$importacaoId} — resumo.json gravado (" . count($resultado) . " parâmetros com erro).");
    }

    private function contabilizarFalhaSpool(array $meta): void
    {
        $importacaoId = (int) $meta['importacao_id'];
        $totalChunk   = (int) $meta['total_chunk'];

        if ($meta['arquivo']) {
            @unlink($meta['arquivo']);
        }

        Importacao::where('id', $importacaoId)->increment('linhas_processadas', $totalChunk);
        Importacao::where('id', $importacaoId)->increment('linhas_com_erro', $totalChunk);

        $this->verificarConclusao($importacaoId);
    }

    private function removerDiretorioSeVazio(string $dir): void
    {
        if (is_dir($dir) && count(scandir($dir)) === 2) {
            @rmdir($dir);
        }
    }
}
