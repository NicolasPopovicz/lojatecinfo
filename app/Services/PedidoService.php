<?php

namespace App\Services;

use App\DTO\Pedido\PedidoDTO;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class PedidoService
{
    public const OPCOES_POR_PAGINA = [15, 25, 50, 100, 500];
    public const POR_PAGINA_PADRAO = 15;

    public function __construct(private readonly Pedido $pedido) {}

    public function listar(?string $busca = null, int $porPagina = self::POR_PAGINA_PADRAO): LengthAwarePaginator
    {
        return $this->pedido
            ->when($busca, fn ($q) => $q->where('nomecliente', 'ilike', "%{$busca}%"))
            ->orderBy('id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function buscarPorId(int $id): Pedido
    {
        return $this->pedido->findOrFail($id);
    }

    public function criar(PedidoDTO $dto): Pedido
    {
        return $this->pedido->create([
            'descricao'   => $dto->descricao,
            'nomecliente' => $dto->nomecliente,
            'produto'     => $dto->produto,
            'preco'       => $dto->preco,
            'quantidade'  => $dto->quantidade,
            'total'       => $dto->preco * $dto->quantidade,
        ]);
    }

    public function atualizar(Pedido $pedido, PedidoDTO $dto): Pedido
    {
        $pedido->update([
            'descricao'   => $dto->descricao,
            'nomecliente' => $dto->nomecliente,
            'produto'     => $dto->produto,
            'preco'       => $dto->preco,
            'quantidade'  => $dto->quantidade,
            'total'       => $dto->preco * $dto->quantidade,
        ]);

        return $pedido->fresh();
    }

    public function deletar(Pedido $pedido): void
    {
        $pedido->delete();
    }

    /**
     * Exportação CSV — usa DB::table() para evitar hydration de 7M+ objetos Eloquent.
     * lazy(2000) faz chunks de 2000 linhas via keyset pagination, sem manter cursor aberto.
     */
    public function cursorParaExportacao(?string $busca = null): LazyCollection
    {
        $query = DB::table('pedidos')->orderBy('id');

        if ($busca) {
            $query->whereILike('nomecliente', "%{$busca}%");
        }

        return $query->lazy(2000);
    }
}
