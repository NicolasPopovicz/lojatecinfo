<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

abstract class ApiException extends Exception
{
    public function __construct(
        string $message = '',
        private readonly string $tipo = 'erro_interno',
        private readonly int $status = 500,
    ) {
        parent::__construct($message ?: $this->mensagemPadrao());
    }

    /** Mensagem exibida quando nenhuma for passada no construtor. */
    protected function mensagemPadrao(): string
    {
        return 'Ocorreu um erro inesperado.';
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'tipo'     => $this->tipo,
            'mensagem' => $this->getMessage(),
        ], $this->status);
    }
}
