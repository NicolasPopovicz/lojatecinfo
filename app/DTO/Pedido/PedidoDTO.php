<?php

namespace App\DTO\Pedido;

use App\Http\Requests\Pedido\PedidoRequest;

class PedidoDTO
{
    public function __construct(
        public readonly string $descricao,
        public readonly string $nomecliente,
        public readonly string $produto,
        public readonly float  $preco,
        public readonly int    $quantidade,
    ) {}

    public static function fromRequest(PedidoRequest $request): self
    {
        return new self(
            descricao:   $request->validated('descricao'),
            nomecliente: $request->validated('nomecliente'),
            produto:     $request->validated('produto'),
            preco:       (float) $request->validated('preco'),
            quantidade:  (int)   $request->validated('quantidade'),
        );
    }
}
