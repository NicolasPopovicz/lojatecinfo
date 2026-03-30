<?php

namespace App\DTO\Transportadora;

use App\Http\Requests\Transportadora\TransportadoraListagemRequest;
use App\Services\TransportadoraService;

class TransportadoraListagemDTO
{
    public function __construct(
        public readonly ?string $busca,
        public readonly int     $porPagina,
    ) {}

    public static function fromRequest(TransportadoraListagemRequest $request): self
    {
        return new self(
            busca:     $request->validated('busca') ?: null,
            porPagina: (int) ($request->validated('por_pagina') ?? TransportadoraService::POR_PAGINA_PADRAO),
        );
    }
}
