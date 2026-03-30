<?php

namespace App\Exceptions;

class PedidoNaoEncontradoException extends ApiException
{
    public function __construct(string $mensagem = '')
    {
        parent::__construct($mensagem, tipo: 'pedido_nao_encontrado', status: 404);
    }

    protected function mensagemPadrao(): string
    {
        return 'Pedido não encontrado.';
    }
}
