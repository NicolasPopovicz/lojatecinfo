<?php

namespace App\DTO\Importacao;

use App\Http\Requests\Importacao\ImportacaoHistoricoJsonRequest;

class ImportacaoHistoricoJsonDTO
{
    public function __construct(
        public readonly array $ids,
    ) {}

    public static function fromRequest(ImportacaoHistoricoJsonRequest $request): self
    {
        $ids = array_filter(
            array_map('intval', explode(',', $request->validated('ids') ?? ''))
        );

        return new self(ids: array_values($ids));
    }
}
