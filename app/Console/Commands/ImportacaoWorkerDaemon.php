<?php

namespace App\Console\Commands;

use App\Enums\StatusImportacao;
use App\Models\Importacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ImportacaoWorkerDaemon extends Command
{
    protected $signature   = 'importacao:worker';
    protected $description = 'Daemon worker que valida chunks e grava spool CSV para carregamento';

    private const MAX_AMOSTRAS_ERRO = 10; // por chunk; spool daemon acumula até 100

    private string $tag;

    public function handle(): void
    {
        $this->tag = '[worker:' . getmypid() . ']';

        Log::info("{$this->tag} Daemon iniciado.");

        while (true) {
            $resultado = Redis::blpop(ImportacaoReaderDaemon::FILA_WORK, 2);

            if (!$resultado) {
                continue;
            }

            [, $item] = $resultado;
            [$importacaoId, $numeroChunk] = explode(':', $item, 2);
            $importacaoId = (int) $importacaoId;
            $numeroChunk  = (int) $numeroChunk;

            try {
                $this->processarChunk($importacaoId, $numeroChunk);
            } catch (Throwable $e) {
                Log::error("{$this->tag} Falha no chunk #{$numeroChunk} da importação #{$importacaoId}: {$e->getMessage()}");
                $this->encaminharFalhaChunk($importacaoId, $numeroChunk);
            }
        }
    }

    private function processarChunk(int $importacaoId, int $numeroChunk): void
    {
        $chaveRedis = "importacao:{$importacaoId}:lote:{$numeroChunk}";

        $status = Importacao::where('id', $importacaoId)->value('status');

        // Importação removida ou falhou — descarta o chunk sem processar
        if ($status === null || $status === StatusImportacao::Falhou->value) {
            Redis::del($chaveRedis);
            return;
        }

        if ($status === StatusImportacao::Pausada->value) {
            Log::info("{$this->tag} Importação #{$importacaoId} pausada. Re-enfileirando chunk #{$numeroChunk}.");
            sleep(5);
            Redis::rpush(ImportacaoReaderDaemon::FILA_WORK, "{$importacaoId}:{$numeroChunk}");
            return;
        }

        $conteudo = Redis::get($chaveRedis);

        if (!$conteudo) {
            Log::warning("{$this->tag} Chunk #{$numeroChunk} da importação #{$importacaoId} não encontrado no Redis (TTL expirado?).");
            return;
        }

        $linhas = json_decode($conteudo, associative: true) ?? [];

        if (empty($linhas)) {
            Redis::del($chaveRedis);
            return;
        }

        $inicio     = microtime(true);
        $totalChunk = count($linhas);

        Log::info("{$this->tag} #{$importacaoId} chunk #{$numeroChunk} — {$totalChunk} linhas. Validando...");

        [$validas, $erros] = $this->validar($linhas);

        $qtdValidas = count($validas);
        $qtdErros   = count($erros);

        $arquivo = null;

        if ($qtdValidas > 0) {
            $arquivo = $this->escreverSpool($validas, $importacaoId, $numeroChunk);
        }

        Redis::rpush(ImportacaoSpoolDaemon::FILA_SPOOL, json_encode([
            'importacao_id' => $importacaoId,
            'arquivo'       => $arquivo,
            'total_chunk'   => $totalChunk,
            'validas'       => $qtdValidas,
            'erros'         => $qtdErros,
            'amostras_erro' => array_slice($erros, 0, self::MAX_AMOSTRAS_ERRO),
        ]));

        Redis::del($chaveRedis);

        $duracao = round(microtime(true) - $inicio, 2);
        Log::info("{$this->tag} #{$importacaoId} chunk #{$numeroChunk} — {$duracao}s | ok: {$qtdValidas} | erros: {$qtdErros}");
    }

    /**
     * Escreve as linhas válidas em CSV para carregamento via COPY.
     * O arquivo precisa ser legível pelo processo do PostgreSQL (0644).
     */
    private function escreverSpool(array $validas, int $importacaoId, int $numeroChunk): string
    {
        $dir = storage_path("importacoes/spool/{$importacaoId}");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $arquivo = "{$dir}/{$numeroChunk}.csv";
        $fh      = fopen($arquivo, 'w');

        foreach ($validas as $row) {
            fputcsv($fh, [
                $row['descricao'],
                $row['nomecliente'],
                $row['produto'],
                $row['preco'],
                $row['quantidade'],
                $row['total'],
                $row['created_at'],
                $row['updated_at'],
            ]);
        }

        fclose($fh);
        chmod($arquivo, 0644);

        return $arquivo;
    }

    /**
     * Quando o worker falha antes de gravar o spool, encaminha o chunk
     * como erro total para o spool daemon atualizar os contadores.
     */
    private function encaminharFalhaChunk(int $importacaoId, int $numeroChunk): void
    {
        $chaveRedis = "importacao:{$importacaoId}:lote:{$numeroChunk}";
        $conteudo   = Redis::get($chaveRedis);
        $count      = $conteudo ? count(json_decode($conteudo, true) ?? []) : 0;

        Redis::rpush(ImportacaoSpoolDaemon::FILA_SPOOL, json_encode([
            'importacao_id' => $importacaoId,
            'arquivo'       => null,
            'total_chunk'   => $count,
            'validas'       => 0,
            'erros'         => $count,
            'amostras_erro' => [],
        ]));

        Redis::del($chaveRedis);
    }

    private function validar(array $linhas): array
    {
        $validas = [];
        $erros   = [];
        $agora   = now()->toDateTimeString();

        foreach ($linhas as $idx => $linha) {
            $e = $this->validarLinha($linha);

            if (!empty($e)) {
                $erros[] = ['linha' => $idx + 1, 'dados' => $linha, 'erros' => $e];
                continue;
            }

            $preco      = (float) str_replace(',', '.', $linha['preco']);
            $quantidade = (int) $linha['quantidade'];

            $validas[] = [
                'descricao'   => $linha['descricao'],
                'nomecliente' => $linha['nomecliente'],
                'produto'     => $linha['produto'],
                'preco'       => $preco,
                'quantidade'  => $quantidade,
                'total'       => round($preco * $quantidade, 2),
                'created_at'  => $agora,
                'updated_at'  => $agora,
            ];
        }

        return [$validas, $erros];
    }

    private function validarLinha(array $linha): array
    {
        $erros = [];

        $descricao = $linha['descricao'] ?? '';
        $len = mb_strlen($descricao);
        if ($len < 3 || $len > 120) {
            $erros['descricao'] = $len === 0
                ? ['Descrição obrigatória.']
                : ["Descrição deve ter entre 3 e 120 caracteres (atual: {$len})."];
        }

        $nomecliente = $linha['nomecliente'] ?? '';
        if ($nomecliente === '') {
            $erros['nomecliente'] = ['Nome do cliente obrigatório.'];
        } elseif (mb_strlen($nomecliente) > 100) {
            $erros['nomecliente'] = ['Nome muito longo (máx. 100 caracteres).'];
        }

        $produto = $linha['produto'] ?? '';
        if ($produto === '') {
            $erros['produto'] = ['Produto obrigatório.'];
        } elseif (mb_strlen($produto) > 70) {
            $erros['produto'] = ['Produto muito longo (máx. 70 caracteres).'];
        }

        $preco = $linha['preco'] ?? '';
        if ($preco === '') {
            $erros['preco'] = ['Preço obrigatório.'];
        } elseif (!preg_match('/^\d+([.,]\d{1,2})?$/', $preco)) {
            $erros['preco'] = ['Preço inválido. Use 9999.99 ou 9999,99.'];
        }

        $quantidade = $linha['quantidade'] ?? '';
        if ($quantidade === '') {
            $erros['quantidade'] = ['Quantidade obrigatória.'];
        } elseif (!ctype_digit((string) $quantidade) || (int) $quantidade < 1 || (int) $quantidade > 9999) {
            $erros['quantidade'] = ['Quantidade deve ser inteira entre 1 e 9999.'];
        }

        return $erros;
    }
}
