<?php

namespace App\Http\Controllers;

use App\DTO\Transportadora\TransportadoraDTO;
use App\Http\Requests\Transportadora\TransportadoraRequest;
use App\Models\Transportadora;
use App\Services\TransportadoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransportadorasController extends Controller
{
    public function __construct(private readonly TransportadoraService $service) {}

    public function index(Request $request): View
    {
        $busca           = $request->string('busca')->toString() ?: null;
        $porPagina       = $this->porPaginaValido($request);
        $transportadoras = $this->service->listar($busca, $porPagina);

        return view('transportadoras.index', compact('transportadoras', 'busca', 'porPagina'));
    }

    private function porPaginaValido(Request $request): int
    {
        $valor = (int) $request->query('por_pagina', TransportadoraService::POR_PAGINA_PADRAO);
        return in_array($valor, TransportadoraService::OPCOES_POR_PAGINA, true)
            ? $valor
            : TransportadoraService::POR_PAGINA_PADRAO;
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

    /**
     * Endpoint AJAX — preenche campos de endereço via CEP.
     */
    public function buscarCep(Request $request): JsonResponse
    {
        $cep = $request->string('cep')->toString();

        $endereco = $this->service->buscarEnderecoPorCep($cep);

        if ($endereco === null) {
            return response()->json(['erro' => 'CEP não encontrado.'], 404);
        }

        return response()->json($endereco);
    }
}
