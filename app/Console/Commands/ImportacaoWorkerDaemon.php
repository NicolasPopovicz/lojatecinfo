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
    protected $description = 'Daemon worker que valida chunks, grava spool CSV e JSONL de erros';

    private const VALORES_INVALIDOS = ['null', 'undefined', 'n/a', 'n/d', '###', '---', 'none', 'nil', 'na', 'nd'];

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

        [$validas, $invalidas] = $this->validar($linhas);

        $qtdValidas = count($validas);
        $qtdErros   = count($invalidas);
        $arquivo    = null;

        if ($qtdValidas > 0) {
            $arquivo = $this->escreverSpool($validas, $importacaoId, $numeroChunk);
        }

        if ($qtdErros > 0) {
            $this->escreverErros($invalidas, $importacaoId, $numeroChunk);
        }

        Redis::rpush(ImportacaoSpoolDaemon::FILA_SPOOL, json_encode([
            'importacao_id' => $importacaoId,
            'arquivo'       => $arquivo,
            'total_chunk'   => $totalChunk,
            'validas'       => $qtdValidas,
            'erros'         => $qtdErros,
        ]));

        Redis::del($chaveRedis);

        $duracao = round(microtime(true) - $inicio, 2);
        Log::info("{$this->tag} #{$importacaoId} chunk #{$numeroChunk} — {$duracao}s | ok: {$qtdValidas} | erros: {$qtdErros}");
    }

    private function validar(array $linhas): array
    {
        $validas   = [];
        $invalidas = [];
        $agora     = now()->toDateTimeString();

        foreach ($linhas as $dados) {
            $erros = $this->validarLinha($dados);

            if (!empty($erros)) {
                $invalidas[] = ['dados' => $dados, 'erros' => $erros];
                continue;
            }

            $preco      = (float) str_replace(',', '.', $dados['preco']);
            $quantidade = (int) $dados['quantidade'];

            $validas[] = [
                $dados['descricao'],
                $dados['nomecliente'],
                $dados['produto'],
                $preco,
                $quantidade,
                round($preco * $quantidade, 2),
                $agora,
                $agora,
            ];
        }

        return [$validas, $invalidas];
    }

    private function validarLinha(array $linha): array
    {
        $erros = [];

        $descricao = $linha['descricao'] ?? '';
        $len       = mb_strlen($descricao);
        if ($len < 3) {
            $erros['descricao'] = [$len === 0 ? 'Descrição obrigatória.' : 'Descrição muito curta (mín. 3 caracteres).'];
        } elseif ($len > 120) {
            $erros['descricao'] = ['Descrição muito longa (máx. 120 caracteres).'];
        }

        $nomecliente = $linha['nomecliente'] ?? '';
        $lenNome     = mb_strlen($nomecliente);
        if ($lenNome === 0) {
            $erros['nomecliente'] = ['Nome do cliente obrigatório.'];
        } elseif ($lenNome < 3) {
            $erros['nomecliente'] = ['Nome do cliente muito curto (mín. 3 caracteres).'];
        } elseif ($lenNome > 100) {
            $erros['nomecliente'] = ['Nome do cliente muito longo (máx. 100 caracteres).'];
        } elseif (in_array(mb_strtolower(trim($nomecliente)), self::VALORES_INVALIDOS, true)) {
            $erros['nomecliente'] = ['Nome do cliente inválido (valor reservado).'];
        } elseif (!preg_match('/\p{L}{2}/u', $nomecliente)) {
            $erros['nomecliente'] = ['Nome do cliente deve conter pelo menos duas letras consecutivas.'];
        }

        $produto = $linha['produto'] ?? '';
        $lenProd = mb_strlen($produto);
        if ($lenProd === 0) {
            $erros['produto'] = ['Produto obrigatório.'];
        } elseif ($lenProd < 2) {
            $erros['produto'] = ['Nome do produto muito curto (mín. 2 caracteres).'];
        } elseif ($lenProd > 70) {
            $erros['produto'] = ['Produto muito longo (máx. 70 caracteres).'];
        } elseif (in_array(mb_strtolower(trim($produto)), self::VALORES_INVALIDOS, true)) {
            $erros['produto'] = ['Produto inválido (valor reservado).'];
        } elseif (!preg_match('/\p{L}{2}/u', $produto)) {
            $erros['produto'] = ['Produto deve conter pelo menos duas letras consecutivas.'];
        }

        $preco = $linha['preco'] ?? '';
        if ($preco === '') {
            $erros['preco'] = ['Preço obrigatório.'];
        } elseif (!preg_match('/^\d+([.,]\d{1,2})?$/', $preco)) {
            $erros['preco'] = ['Preço inválido. Use o formato 9999.99 ou 9999,99.'];
        } elseif ((float) str_replace(',', '.', $preco) <= 0) {
            $erros['preco'] = ['Preço deve ser maior que zero.'];
        }

        $quantidade = $linha['quantidade'] ?? '';
        if ($quantidade === '') {
            $erros['quantidade'] = ['Quantidade obrigatória.'];
        } elseif (!ctype_digit((string) $quantidade)) {
            $erros['quantidade'] = ['Quantidade inválida. Use somente números inteiros.'];
        } elseif ((int) $quantidade < 1 || (int) $quantidade > 9999) {
            $erros['quantidade'] = ['Quantidade deve ser um inteiro entre 1 e 9999.'];
        }

        return $erros;
    }

    private function escreverSpool(array $validas, int $importacaoId, int $numeroChunk): string
    {
        $dir = storage_path("importacoes/spool/{$importacaoId}");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        $arquivo = "{$dir}/{$numeroChunk}.csv";
        $fh      = fopen($arquivo, 'w');

        foreach ($validas as $row) {
            fputcsv($fh, $row);
        }

        fclose($fh);

        return $arquivo;
    }

    private function escreverErros(array $invalidas, int $importacaoId, int $numeroChunk): void
    {
        $dir = storage_path("importacoes/erros/{$importacaoId}");

        @mkdir($dir, 0755, true);

        $fh = fopen("{$dir}/{$numeroChunk}.jsonl", 'w');

        foreach ($invalidas as $item) {
            fwrite($fh, json_encode(
                ['dados' => $item['dados'], 'erros' => $item['erros']],
                JSON_UNESCAPED_UNICODE
            ) . "\n");
        }

        fclose($fh);
    }

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
        ]));

        Redis::del($chaveRedis);
    }
}
