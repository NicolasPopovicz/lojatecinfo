<?php

namespace App\Http\Controllers;

use App\DTO\Pedido\PedidoDTO;
use App\DTO\Pedido\PedidoListagemDTO;
use App\Http\Requests\Pedido\PedidoListagemRequest;
use App\Http\Requests\Pedido\PedidoRequest;
use App\Models\Pedido;
use App\Services\PedidoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PedidosController extends Controller
{
    public function __construct(private readonly PedidoService $servico) {}

    public function index(PedidoListagemRequest $request): View
    {
        $dto     = PedidoListagemDTO::fromRequest($request);
        $pedidos = $this->servico->listar($dto->busca, $dto->porPagina);

        return view('pedidos.index', [
            'pedidos'   => $pedidos,
            'busca'     => $dto->busca,
            'porPagina' => $dto->porPagina,
        ]);
    }

    public function create(): View
    {
        return view('pedidos.create');
    }

    public function store(PedidoRequest $request): RedirectResponse
    {
        $this->servico->criar(PedidoDTO::fromRequest($request));

        return redirect()
            ->route('pedidos.index')
            ->with('sucesso', 'Pedido cadastrado com sucesso.');
    }

    public function show(Pedido $pedido): View
    {
        if (request()->ajax()) {
            return view('pedidos._detalhe', compact('pedido'));
        }

        return view('pedidos.show', compact('pedido'));
    }

    public function edit(Pedido $pedido): View
    {
        return view('pedidos.edit', compact('pedido'));
    }

    public function update(PedidoRequest $request, Pedido $pedido): RedirectResponse
    {
        $this->servico->atualizar($pedido, PedidoDTO::fromRequest($request));

        return redirect()
            ->route('pedidos.index')
            ->with('sucesso', 'Pedido atualizado com sucesso.');
    }

    public function destroy(Pedido $pedido): RedirectResponse
    {
        $this->servico->deletar($pedido);

        return redirect()
            ->route('pedidos.index')
            ->with('sucesso', 'Pedido excluído com sucesso.');
    }

    public function exportarCsv(PedidoListagemRequest $request): StreamedResponse
    {
        return $this->servico->streamExportacaoCsv(
            PedidoListagemDTO::fromRequest($request)->busca
        );
    }
}
