<?php

namespace App\Http\Controllers;

use App\DTO\Transportadora\BuscarCepDTO;
use App\DTO\Transportadora\TransportadoraDTO;
use App\DTO\Transportadora\TransportadoraListagemDTO;
use App\Exceptions\CepNaoEncontradoException;
use App\Http\Requests\Transportadora\BuscarCepRequest;
use App\Http\Requests\Transportadora\TransportadoraListagemRequest;
use App\Http\Requests\Transportadora\TransportadoraRequest;
use App\Models\Transportadora;
use App\Services\TransportadoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TransportadorasController extends Controller
{
    public function __construct(private readonly TransportadoraService $service) {}

    public function index(TransportadoraListagemRequest $request): View
    {
        $dto             = TransportadoraListagemDTO::fromRequest($request);
        $transportadoras = $this->service->listar($dto->busca, $dto->porPagina);

        return view('transportadoras.index', [
            'transportadoras' => $transportadoras,
            'busca'           => $dto->busca,
            'porPagina'       => $dto->porPagina,
        ]);
    }

    public function create(): View
    {
        return view('transportadoras.create');
    }

    public function store(TransportadoraRequest $request): RedirectResponse
    {
        $this->service->criar(TransportadoraDTO::fromRequest($request));

        return redirect()->route('transportadoras.index')
            ->with('sucesso', 'Transportadora cadastrada com sucesso.');
    }

    public function show(Transportadora $transportadora): View
    {
        if (request()->ajax()) {
            return view('transportadoras._detalhe', compact('transportadora'));
        }

        return view('transportadoras.show', compact('transportadora'));
    }

    public function edit(Transportadora $transportadora): View
    {
        return view('transportadoras.edit', compact('transportadora'));
    }

    public function update(TransportadoraRequest $request, Transportadora $transportadora): RedirectResponse
    {
        $this->service->atualizar($transportadora, TransportadoraDTO::fromRequest($request));

        return redirect()->route('transportadoras.index')
            ->with('sucesso', 'Transportadora atualizada com sucesso.');
    }

    public function destroy(Transportadora $transportadora): RedirectResponse
    {
        $this->service->deletar($transportadora);

        return redirect()->route('transportadoras.index')
            ->with('sucesso', 'Transportadora removida com sucesso.');
    }

    public function buscarCep(BuscarCepRequest $request): JsonResponse
    {
        $endereco = $this->service->buscarEnderecoPorCep(BuscarCepDTO::fromRequest($request)->cep);

        if ($endereco === null) {
            throw new CepNaoEncontradoException();
        }

        return response()->json($endereco);
    }
}
