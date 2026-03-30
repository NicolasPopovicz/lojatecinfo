<?php

namespace App\DTO\Transportadora;

use App\Http\Requests\Transportadora\BuscarCepRequest;

class BuscarCepDTO
{
    public function __construct(
        public readonly string $cep,
    ) {}

    public static function fromRequest(BuscarCepRequest $request): self
    {
        return new self(
            cep: $request->validated('cep'),
        );
    }
}
