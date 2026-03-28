<?php

namespace App\Enums;

enum StatusImportacao: string
{
    case Pendente    = 'pendente';
    case Lendo       = 'lendo';       // reader lendo o CSV e distribuindo chunks
    case Processando = 'processando'; // workers validando + spool daemon carregando
    case Pausada     = 'pausada';
    case Concluido   = 'concluido';
    case Cancelada   = 'cancelada';
    case Falhou      = 'falhou';

    public function rotulo(): string
    {
        return match($this) {
            self::Pendente    => 'Pendente',
            self::Lendo       => 'Lendo arquivo',
            self::Processando => 'Processando',
            self::Pausada     => 'Pausada',
            self::Concluido   => 'Concluído',
            self::Cancelada   => 'Cancelada',
            self::Falhou      => 'Falhou',
        };
    }

    public function estaEmAndamento(): bool
    {
        return in_array($this, [self::Pendente, self::Lendo, self::Processando, self::Pausada]);
    }

    public function podePausar(): bool
    {
        // Não faz sentido pausar durante a leitura — o reader é rápido e o chunk já está indo para a fila
        return $this === self::Processando;
    }

    public function podeRetomar(): bool
    {
        return $this === self::Pausada;
    }

    public function podeCancelar(): bool
    {
        return $this->estaEmAndamento();
    }
}
