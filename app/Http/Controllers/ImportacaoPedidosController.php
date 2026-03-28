<?php

namespace App\Http\Controllers;

use App\Console\Commands\ImportacaoReaderDaemon;
use App\Enums\StatusImportacao;
use App\Models\Importacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportacaoPedidosController extends Controller
{
    public const OPCOES_POR_PAGINA = [15, 25, 50, 100];
    private const POR_PAGINA_PADRAO = 15;

    public function formulario(): View
    {
        $historico = Importacao::latest()->take(10)->get();

        return view('pedidos.importar', compact('historico'));
    }

    public function listarHistorico(Request $request): View
    {
        $busca     = $request->query('busca');
        $porPagina = in_array((int) $request->query('por_pagina'), self::OPCOES_POR_PAGINA)
            ? (int) $request->query('por_pagina')
            : self::POR_PAGINA_PADRAO;

        $importacoes = Importacao::query()
            ->when($busca, fn ($q) => $q->where('arquivo_original', 'ilike', "%{$busca}%"))
            ->latest()
            ->paginate($porPagina)
            ->withQueryString();

        return view('pedidos.importar-historico', compact('importacoes', 'busca', 'porPagina'));
    }

    public function historico(Request $request): JsonResponse
    {
        $ids   = array_filter(array_map('intval', explode(',', $request->query('ids', ''))));
        $query = Importacao::latest();

        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->take(10);
        }

        $itens = $query->get()->map(fn(Importacao $i) => [
            'id'                 => $i->id,
            'arquivo_original'   => $i->arquivo_original,
            'total_linhas'       => $i->total_linhas,
            'total_linhas_fmt'   => number_format($i->total_linhas, 0, ',', '.'),
            'importadas'         => $i->linhas_processadas - $i->linhas_com_erro,
            'importadas_fmt'     => number_format(max(0, $i->linhas_processadas - $i->linhas_com_erro), 0, ',', '.'),
            'erros'              => $i->linhas_com_erro,
            'erros_fmt'          => number_format($i->linhas_com_erro, 0, ',', '.'),
            'status'             => $i->status->value,
            'status_rotulo'      => $i->status->rotulo(),
            'duracao'            => $i->duracao ?? '—',
            'em_andamento'       => $i->status->estaEmAndamento(),
            'url_acompanhar'     => route('pedidos.importar.acompanhar', $i),
        ]);

        $temAndamento = $itens->contains(fn($i) => $i['em_andamento']);

        return response()->json([
            'itens'        => $itens,
            'tem_andamento' => $temAndamento,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate(
            ['arquivo' => 'required|file|mimes:csv,txt|max:1048576'],
            [
                'arquivo.required' => 'Selecione um arquivo CSV para importar.',
                'arquivo.file'     => 'O arquivo enviado é inválido.',
                'arquivo.mimes'    => 'O arquivo deve estar no formato CSV.',
                'arquivo.max'      => 'O arquivo não pode ultrapassar 1GB.',
            ]
        );

        $arquivo         = $request->file('arquivo');
        $nomeOriginal    = $arquivo->getClientOriginalName();
        $caminho         = $arquivo->store('importacoes/uploads');

        $importacao = Importacao::create([
            'arquivo_original' => $nomeOriginal,
            'caminho'          => $caminho,
            'status'           => StatusImportacao::Pendente,
        ]);

        Redis::rpush(ImportacaoReaderDaemon::FILA_IMPORTACAO, $importacao->id);

        return redirect()
            ->route('pedidos.importar.acompanhar', $importacao)
            ->with('info', 'Arquivo recebido. A importação foi iniciada em segundo plano.');
    }

    public function acompanhar(Importacao $importacao): View
    {
        return view('pedidos.importar-progresso', compact('importacao'));
    }

    /**
     * Endpoint SSE — mantém a conexão aberta e empurra estado a cada 1 segundo.
     */
    public function progresso(Importacao $importacao): StreamedResponse
    {
        return response()->stream(function () use ($importacao) {
            set_time_limit(0);

            while (!connection_aborted()) {
                $importacao->refresh();

                $payload = [
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
                    'amostra_erros'      => $importacao->status === StatusImportacao::Concluido
                                               ? ($importacao->amostra_erros ?? [])
                                               : [],
                ];

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

    public function pausar(Importacao $importacao): RedirectResponse
    {
        if (!$importacao->status->podePausar()) {
            return back()->with('error', 'Esta importação não pode ser pausada.');
        }

        $importacao->update(['status' => StatusImportacao::Pausada]);
        Log::info("[importacao:{$importacao->id}] Pausada pelo usuário.");

        return back()->with('info', 'Importação pausada. Os jobs em andamento terminarão o lote atual antes de parar.');
    }

    public function retomar(Importacao $importacao): RedirectResponse
    {
        if (!$importacao->status->podeRetomar()) {
            return back()->with('error', 'Esta importação não está pausada.');
        }

        $importacao->update(['status' => StatusImportacao::Processando]);
        Log::info("[importacao:{$importacao->id}] Retomada pelo usuário.");

        return back()->with('success', 'Importação retomada.');
    }

    public function cancelar(Importacao $importacao): RedirectResponse
    {
        if (!$importacao->status->podeCancelar()) {
            return back()->with('error', 'Esta importação não pode ser cancelada.');
        }

        // Remove o arquivo CSV enviado
        Storage::delete($importacao->caminho);

        $id = $importacao->id;
        $importacao->delete();

        Log::info("[importacao:{$id}] Cancelada e removida pelo usuário.");

        return redirect()
            ->route('pedidos.importar')
            ->with('warning', "Importação #{$id} cancelada e removida. Os registros já inseridos permanecem na base.");
    }
}
