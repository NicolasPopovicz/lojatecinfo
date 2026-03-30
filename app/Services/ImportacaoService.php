<?php

namespace App\Services;

use App\Console\Commands\ImportacaoReaderDaemon;
use App\DTO\Importacao\ImportacaoUploadDTO;
use App\Enums\StatusImportacao;
use App\Exceptions\ImportacaoException;
use App\Models\Importacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportacaoService
{
    public const OPCOES_POR_PAGINA = [15, 25, 50, 100];
    public const POR_PAGINA_PADRAO = 15;

    public function __construct(private readonly Importacao $importacao) {}

    public function iniciar(ImportacaoUploadDTO $dto): Importacao
    {
        $dir = storage_path('importacoes/uploads');
        @mkdir($dir, 0755, true);

        $filename = $dto->arquivo->hashName();
        $dto->arquivo->move($dir, $filename);

        $importacao = $this->importacao->create([
            'arquivo_original' => $dto->arquivo->getClientOriginalName(),
            'caminho'          => "importacoes/uploads/{$filename}",
            'status'           => StatusImportacao::Pendente,
        ]);

        Redis::rpush(ImportacaoReaderDaemon::FILA_IMPORTACAO, $importacao->id);

        return $importacao;
    }

    public function buildProgressoPayload(Importacao $importacao): array
    {
        return [
            'status'             => $importacao->status->value,
            'status_rotulo'      => $importacao->status->rotulo(),
            'total_linhas'       => $importacao->total_linhas,
            'linhas_processadas' => $importacao->linhas_processadas,
            'linhas_com_erro'    => $importacao->linhas_com_erro,
            'percentual'         => $importacao->percentual,
            'duracao'            => $importacao->duracao,
            'concluido'          => !$importacao->status->estaEmAndamento(),
            'concluido_em'       => $importacao->concluido_em?->format('d/m/Y \à\s H:i:s'),
            'pode_pausar'        => $importacao->status->podePausar(),
            'pode_retomar'       => $importacao->status->podeRetomar(),
            'pode_cancelar'      => $importacao->status->podeCancelar(),
        ];
    }

    public function pausar(Importacao $importacao): void
    {
        if (!$importacao->status->podePausar()) {
            throw new ImportacaoException('Esta importação não pode ser pausada.');
        }

        $importacao->update(['status' => StatusImportacao::Pausada]);
        Log::info("[importacao:{$importacao->id}] Pausada pelo usuário.");
    }

    public function retomar(Importacao $importacao): void
    {
        if (!$importacao->status->podeRetomar()) {
            throw new ImportacaoException('Esta importação não está pausada.');
        }

        $importacao->update(['status' => StatusImportacao::Processando]);
        Log::info("[importacao:{$importacao->id}] Retomada pelo usuário.");
    }

    public function cancelar(Importacao $importacao): void
    {
        if (!$importacao->status->podeCancelar()) {
            throw new ImportacaoException('Esta importação não pode ser cancelada.');
        }

        @unlink(storage_path($importacao->caminho));

        // Remove arquivos de erros de validação, se existirem
        $errosDir = storage_path("importacoes/erros/{$importacao->id}");
        if (is_dir($errosDir)) {
            array_map('unlink', glob("{$errosDir}/*") ?: []);
            @rmdir($errosDir);
        }

        $id = $importacao->id;
        $importacao->delete();

        Log::info("[importacao:{$id}] Cancelada e removida pelo usuário.");
    }

    public function temArquivoErros(Importacao $importacao): bool
    {
        return $importacao->linhas_com_erro > 0
            && $importacao->erros_resumo !== null
            && file_exists(storage_path($importacao->erros_resumo));
    }

    public function listarErrosAgrupados(Importacao $importacao): array
    {
        if (!$importacao->erros_resumo) {
            return [];
        }

        $path = storage_path($importacao->erros_resumo);

        if (!file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    public function streamProgresso(Importacao $importacao): StreamedResponse
    {
        return response()->stream(function () use ($importacao) {
            set_time_limit(0);

            while (!connection_aborted()) {
                $importacao->refresh();

                $payload = $this->buildProgressoPayload($importacao);

                echo 'data: ' . json_encode($payload) . "\n\n";
                flush();

                if ($payload['concluido']) {
                    break;
                }

                sleep(1);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function streamErrosCsv(Importacao $importacao): StreamedResponse
    {
        $dir = storage_path("importacoes/erros/{$importacao->id}");

        return response()->stream(function () use ($dir) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($out, ['descricao', 'nomecliente', 'produto', 'preco', 'quantidade', 'motivo_erro'], ';');

            $arquivos = glob("{$dir}/*.jsonl") ?: [];
            natsort($arquivos);

            foreach ($arquivos as $arquivo) {
                $fh = fopen($arquivo, 'r');
                while (($linha = fgets($fh)) !== false) {
                    $item = json_decode(trim($linha), true);
                    if (!$item) {
                        continue;
                    }

                    $d      = $item['dados'];
                    $motivo = implode(' | ', array_map(
                        fn($msgs, $campo) => "{$campo}: " . (is_array($msgs) ? implode(', ', $msgs) : $msgs),
                        $item['erros'],
                        array_keys($item['erros'])
                    ));

                    fputcsv($out, [
                        $d['descricao']   ?? '',
                        $d['nomecliente'] ?? '',
                        $d['produto']     ?? '',
                        $d['preco']       ?? '',
                        $d['quantidade']  ?? '',
                        $motivo,
                    ], ';');
                }
                fclose($fh);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="erros_importacao_' . $importacao->id . '.csv"',
            'X-Accel-Buffering'   => 'no',
        ]);
    }

    public function listarHistorico(?string $busca, int $porPagina = self::POR_PAGINA_PADRAO): LengthAwarePaginator
    {
        return $this->importacao->query()
            ->when($busca, fn ($q) => $q->where('arquivo_original', 'ilike', "%{$busca}%"))
            ->latest()
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function buscarParaJson(array $ids): Collection
    {
        $query = $this->importacao->latest();

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->take(10);
        }

        return $query->get()->map(fn (Importacao $i) => $this->formatarItemJson($i));
    }

    public function formatarItemJson(Importacao $importacao): array
    {
        return [
            'id'               => $importacao->id,
            'arquivo_original' => $importacao->arquivo_original,
            'total_linhas'     => $importacao->total_linhas,
            'total_linhas_fmt' => number_format($importacao->total_linhas, 0, ',', '.'),
            'importadas'       => $importacao->linhas_processadas - $importacao->linhas_com_erro,
            'importadas_fmt'   => number_format(max(0, $importacao->linhas_processadas - $importacao->linhas_com_erro), 0, ',', '.'),
            'erros'            => $importacao->linhas_com_erro,
            'erros_fmt'        => number_format($importacao->linhas_com_erro, 0, ',', '.'),
            'status'           => $importacao->status->value,
            'status_rotulo'    => $importacao->status->rotulo(),
            'duracao'          => $importacao->duracao ?? '—',
            'em_andamento'     => $importacao->status->estaEmAndamento(),
            'url_acompanhar'   => route('pedidos.importar.acompanhar', $importacao),
        ];
    }
}
