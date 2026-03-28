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

    private const MAX_AMOSTRAS_ERRO = 100;

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
        $amostrasErro = $meta['amostras_erro'] ?? [];

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
                $qtdErros  += 0; // COPY bem-sucedido — erros já contabilizados pelo worker
            }
        }

        if ($arquivo) {
            @unlink($arquivo);
            $this->removerDiretorioSeVazio(dirname($arquivo));
        }

        // Atualiza contadores — único ponto de escrita, evita race condition
        Importacao::where('id', $importacaoId)
            ->increment('linhas_processadas', $totalChunk);

        if ($qtdErros > 0) {
            Importacao::where('id', $importacaoId)
                ->increment('linhas_com_erro', $qtdErros);

            if (!empty($amostrasErro)) {
                $this->registrarAmostrasErro($importacaoId, $amostrasErro);
            }
        }

        $importacao = Importacao::find($importacaoId, ['total_linhas', 'linhas_processadas']);

        if ($importacao && $importacao->total_linhas > 0) {
            $pct = round(($importacao->linhas_processadas / $importacao->total_linhas) * 100, 1);
            Log::info(
                "{$this->tag} #{$importacaoId} COPY ok — {$qtdValidas} linhas inseridas | " .
                "erros: {$qtdErros} | progresso: {$importacao->linhas_processadas}/{$importacao->total_linhas} ({$pct}%)"
            );
        }

        $this->verificarConclusao($importacaoId);
    }

    /**
     * Executa o COPY. Em caso de falha, preserva o CSV e gera um .sql
     * em storage/importacoes/debug/ para análise posterior.
     */
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

    /**
     * Preserva o CSV falho e gera um .sql com o comando para reexecução manual.
     */
    private function gerarArquivosDebug(int $importacaoId, string $csvOriginal, array $meta, string $erro): void
    {
        $dir = storage_path('importacoes/debug');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $ts   = now()->format('Ymd_His');
        $base = "{$dir}/{$importacaoId}_chunk{$meta['total_chunk']}_{$ts}";

        // Preserva o CSV para inspeção
        $csvDebug = "{$base}.csv";
        @copy($csvOriginal, $csvDebug);

        // Gera o .sql para reexecução manual
        $sql = implode("\n", [
            "-- Importação #{$importacaoId} — falha em " . now()->toDateTimeString(),
            "-- Erro: {$erro}",
            "-- Linhas no chunk: {$meta['total_chunk']} | válidas: {$meta['validas']} | erros de validação: {$meta['erros']}",
            "--",
            "-- Para reexecutar (ajuste o caminho se necessário):",
            "COPY pedidos (" . self::COLUNAS_COPY . ")",
            "FROM '{$csvDebug}'",
            "WITH (FORMAT csv);",
        ]);

        file_put_contents("{$base}.sql", $sql);

        Log::error("{$this->tag} Arquivos de debug gravados: {$base}.csv e {$base}.sql");
    }

    /**
     * UPDATE atômico: somente o daemon que fechar a última linha consegue mudar o status.
     */
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

    private function registrarAmostrasErro(int $importacaoId, array $erros): void
    {
        DB::transaction(function () use ($importacaoId, $erros): void {
            $importacao = Importacao::where('id', $importacaoId)
                ->lockForUpdate()
                ->first(['id', 'amostra_erros']);

            if (!$importacao) {
                return;
            }

            $atuais = $importacao->amostra_erros ?? [];
            $vagas  = max(0, self::MAX_AMOSTRAS_ERRO - count($atuais));

            if ($vagas === 0) {
                return;
            }

            $importacao->update([
                'amostra_erros' => array_merge($atuais, array_slice($erros, 0, $vagas)),
            ]);
        });
    }

    /**
     * Quando o COPY falha, conta todas as linhas do chunk como erros
     * para que o progresso avance e a importação possa concluir.
     */
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
        if (is_dir($dir) && count(scandir($dir)) === 2) { // apenas . e ..
            @rmdir($dir);
        }
    }
}
