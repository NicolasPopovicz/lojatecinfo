<?php

namespace App\Http\Controllers;

use App\DTO\Pedido\PedidoDTO;
use App\Http\Requests\Pedido\PedidoRequest;
use App\Models\Pedido;
use App\Services\PedidoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PedidosController extends Controller
{
    public function __construct(private readonly PedidoService $servico) {}

    public function index(Request $request): View
    {
        $busca     = $request->query('busca');
        $porPagina = $this->porPaginaValido($request, PedidoService::OPCOES_POR_PAGINA, PedidoService::POR_PAGINA_PADRAO);
        $pedidos   = $this->servico->listar($busca, $porPagina);

        return view('pedidos.index', compact('pedidos', 'busca', 'porPagina'));
    }

    private function porPaginaValido(Request $request, array $opcoes, int $padrao): int
    {
        $valor = (int) $request->query('por_pagina', $padrao);
        return in_array($valor, $opcoes, true) ? $valor : $padrao;
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

    public function exportarCsv(Request $request): StreamedResponse
    {
        $busca  = $request->query('busca');
        $cursor = $this->servico->cursorParaExportacao($busca);

        $cabecalho = ['ID', 'Descrição', 'Cliente', 'Produto', 'Preço', 'Quantidade', 'Total', 'Criado em'];

        $resposta = response()->stream(function () use ($cursor, $cabecalho) {
            $saida = fopen('php://output', 'w');

            // BOM UTF-8 para compatibilidade com Excel
            fprintf($saida, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($saida, $cabecalho, ';');

            foreach ($cursor as $pedido) {
                fputcsv($saida, [
                    $pedido->id,
                    $pedido->descricao,
                    $pedido->nomecliente,
                    $pedido->produto,
                    number_format($pedido->preco, 2, ',', '.'),
                    $pedido->quantidade,
                    number_format($pedido->total, 2, ',', '.'),
                    $pedido->created_at,
                ], ';');
            }

            fclose($saida);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pedidos_' . now()->format('Ymd_His') . '.csv"',
            'X-Accel-Buffering'   => 'no',
        ]);

        return $resposta;
    }
}
