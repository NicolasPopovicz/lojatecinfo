<?php

namespace App\DTO\Importacao;

use App\Http\Requests\Importacao\ImportacaoHistoricoRequest;
use App\Services\ImportacaoService;

class ImportacaoHistoricoDTO
{
    public function __construct(
        public readonly ?string $busca,
        public readonly int     $porPagina,
    ) {}

    public static function fromRequest(ImportacaoHistoricoRequest $request): self
    {
        return new self(
            busca:     $request->validated('busca') ?: null,
            porPagina: (int) ($request->validated('por_pagina') ?? ImportacaoService::POR_PAGINA_PADRAO),
        );
    }
}
