<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Base para exceções do sistema web.
 * Requisições JSON → resposta JSON limpa.
 * Requisições web → redirect()->back() com flash 'erro'.
 */
abstract class AppException extends Exception
{
    public function __construct(
        string $message = '',
        private readonly string $tipo = 'erro',
        private readonly int $status = 422,
    ) {
        parent::__construct($message ?: $this->mensagemPadrao());
    }

    protected function mensagemPadrao(): string
    {
        return 'Ocorreu um erro inesperado.';
    }

    public function render(): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson()) {
            return response()->json([
                'tipo'     => $this->tipo,
                'mensagem' => $this->getMessage(),
            ], $this->status);
        }

        return redirect()->back()->with('erro', $this->getMessage());
    }
}
