<?php

namespace App\Http\Controllers\Api;

use App\DTO\Pedido\PedidoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\PedidoRequest;
use App\Services\PedidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedidosApiController extends Controller
{
    public function __construct(private readonly PedidoService $service) {}

    /**
     * Lista pedidos com paginação (50/página) e busca opcional por cliente.
     *
     * GET /api/pedidos?busca=João&page=2
     */
    public function index(Request $request): JsonResponse
    {
        $busca   = $request->string('busca')->toString() ?: null;
        $pedidos = $this->service->listar($busca);

        return response()->json($pedidos);
    }

    /**
     * Retorna um pedido pelo ID.
     *
     * GET /api/pedidos/{id}
     */
    public function show(int $id): JsonResponse
    {
        $pedido = $this->service->buscarPorId($id);

        return response()->json($pedido);
    }

    /**
     * Cria um novo pedido.
     *
     * POST /api/pedidos
     * Body (JSON): descricao, nomecliente, produto, preco, quantidade
     */
    public function store(PedidoRequest $request): JsonResponse
    {
        $pedido = $this->service->criar(PedidoDTO::fromRequest($request));

        return response()->json($pedido, 201);
    }
}
