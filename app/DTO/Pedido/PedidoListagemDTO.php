<?php

namespace App\DTO\Pedido;

use App\Http\Requests\Pedido\PedidoListagemRequest;
use App\Services\PedidoService;

class PedidoListagemDTO
{
    public function __construct(
        public readonly ?string $busca,
        public readonly int     $porPagina,
    ) {}

    public static function fromRequest(PedidoListagemRequest $request): self
    {
        return new self(
            busca:     $request->validated('busca') ?: null,
            porPagina: (int) ($request->validated('por_pagina') ?? PedidoService::POR_PAGINA_PADRAO),
        );
    }
}
