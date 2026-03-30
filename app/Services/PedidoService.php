<?php

namespace App\Services;

use App\DTO\Pedido\PedidoDTO;
use App\Exceptions\PedidoNaoEncontradoException;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        return $this->pedido->find($id) ?? throw new PedidoNaoEncontradoException();
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

    public function streamExportacaoCsv(?string $busca): StreamedResponse
    {
        $cursor    = $this->cursorParaExportacao($busca);
        $cabecalho = ['ID', 'Descrição', 'Cliente', 'Produto', 'Preço', 'Quantidade', 'Total', 'Criado em'];
        $nome      = 'pedidos_' . now()->format('Ymd_His') . '.csv';

        return response()->stream(function () use ($cursor, $cabecalho) {
            $saida = fopen('php://output', 'w');

            fprintf($saida, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
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
            'Content-Disposition' => "attachment; filename=\"{$nome}\"",
            'X-Accel-Buffering'   => 'no',
        ]);
    }
}
