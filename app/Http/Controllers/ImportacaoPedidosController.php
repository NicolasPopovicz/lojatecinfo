<?php

namespace App\Http\Controllers;

use App\DTO\Importacao\ImportacaoHistoricoDTO;
use App\DTO\Importacao\ImportacaoHistoricoJsonDTO;
use App\DTO\Importacao\ImportacaoUploadDTO;
use App\Http\Requests\Importacao\ImportacaoHistoricoJsonRequest;
use App\Http\Requests\Importacao\ImportacaoHistoricoRequest;
use App\Http\Requests\Importacao\ImportacaoUploadRequest;
use App\Models\Importacao;
use App\Services\ImportacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportacaoPedidosController extends Controller
{
    public function __construct(private readonly ImportacaoService $service) {}

    public function formulario(): View
    {
        $historico = Importacao::latest()->take(10)->get();

        return view('pedidos.importar', compact('historico'));
    }

    public function listarHistorico(ImportacaoHistoricoRequest $request): View
    {
        $dto         = ImportacaoHistoricoDTO::fromRequest($request);
        $importacoes = $this->service->listarHistorico($dto->busca, $dto->porPagina);

        return view('pedidos.importar-historico', [
            'importacoes' => $importacoes,
            'busca'       => $dto->busca,
            'porPagina'   => $dto->porPagina,
        ]);
    }

    public function historico(ImportacaoHistoricoJsonRequest $request): JsonResponse
    {
        $itens = $this->service->buscarParaJson(ImportacaoHistoricoJsonDTO::fromRequest($request)->ids);

        return response()->json([
            'itens'         => $itens,
            'tem_andamento' => $itens->contains(fn ($i) => $i['em_andamento']),
        ]);
    }

    public function upload(ImportacaoUploadRequest $request): RedirectResponse
    {
        $importacao = $this->service->iniciar(ImportacaoUploadDTO::fromRequest($request));

        return redirect()
            ->route('pedidos.importar.acompanhar', $importacao)
            ->with('info', 'Arquivo recebido. A importação foi iniciada em segundo plano.');
    }

    public function acompanhar(Importacao $importacao): View
    {
        return view('pedidos.importar-progresso', compact('importacao'));
    }

    public function erros(Importacao $importacao): View
    {
        return view('pedidos.importar-erros', [
            'importacao'      => $importacao,
            'temArquivoErros' => $this->service->temArquivoErros($importacao),
            'errosAgrupados'  => $this->service->listarErrosAgrupados($importacao),
        ]);
    }

    public function exportarErros(Importacao $importacao): StreamedResponse
    {
        return $this->service->streamErrosCsv($importacao);
    }

    public function progresso(Importacao $importacao): StreamedResponse
    {
        return $this->service->streamProgresso($importacao);
    }

    public function pausar(Importacao $importacao): RedirectResponse
    {
        $this->service->pausar($importacao);

        return back()->with('info', 'Importação pausada. Os jobs em andamento terminarão o lote atual antes de parar.');
    }

    public function retomar(Importacao $importacao): RedirectResponse
    {
        $this->service->retomar($importacao);

        return back()->with('success', 'Importação retomada.');
    }

    public function cancelar(Importacao $importacao): RedirectResponse
    {
        $id = $importacao->id;
        $this->service->cancelar($importacao);

        return redirect()
            ->route('pedidos.importar')
            ->with('warning', "Importação #{$id} cancelada e removida. Os registros já inseridos permanecem na base.");
    }
}
