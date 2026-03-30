<?php

namespace App\Exceptions;

class ImportacaoException extends AppException
{
    public function __construct(string $mensagem = '')
    {
        parent::__construct($mensagem, tipo: 'importacao_estado_invalido', status: 422);
    }

    protected function mensagemPadrao(): string
    {
        return 'Operação inválida para o estado atual da importação.';
    }
}
