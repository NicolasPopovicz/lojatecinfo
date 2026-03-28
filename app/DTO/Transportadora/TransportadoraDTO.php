<?php

namespace App\DTO\Transportadora;

use App\Http\Requests\Transportadora\TransportadoraRequest;

class TransportadoraDTO
{
    public function __construct(
        public readonly string $nome,
        public readonly string $cnpj,
        public readonly string $bairro,
        public readonly string $cidade,
        public readonly string $estado,
        public readonly string $rua,
        public readonly string $numero,
        public readonly ?string $complemento
    ) {}

    public static function fromRequest(TransportadoraRequest $request): self
    {
        return new self(
            nome:        $request->validated('nome'),
            cnpj:        $request->validated('cnpj'),
            bairro:      $request->validated('bairro'),
            cidade:      $request->validated('cidade'),
            estado:      $request->validated('estado'),
            rua:         $request->validated('rua'),
            numero:      $request->validated('numero'),
            complemento: $request->validated('complemento')
        );
    }
}