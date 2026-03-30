<?php

namespace App\Exceptions;

class CepNaoEncontradoException extends ApiException
{
    public function __construct(string $mensagem = '')
    {
        parent::__construct($mensagem, tipo: 'cep_nao_encontrado', status: 404);
    }

    protected function mensagemPadrao(): string
    {
        return 'CEP não encontrado.';
    }
}
