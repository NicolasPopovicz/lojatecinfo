<?php

namespace App\Models;

use App\Enums\StatusImportacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'arquivo_original', 'caminho', 'batch_id', 'total_linhas',
    'linhas_processadas', 'linhas_com_erro', 'status', 'amostra_erros',
    'iniciado_em', 'concluido_em'
])]
class Importacao extends Model
{
    protected $table = 'importacoes';
    protected function casts(): array
    {
        return [
            'status'        => StatusImportacao::class,
            'amostra_erros' => 'array',
            'iniciado_em'   => 'datetime',
            'concluido_em'  => 'datetime',
        ];
    }

    public function getPercentualAttribute(): float
    {
        if ($this->total_linhas === 0) {
            return 0.0;
        }

        return round(($this->linhas_processadas / $this->total_linhas) * 100, 1);
    }

    public function getDuracaoAttribute(): ?string
    {
        if (is_null($this->iniciado_em)) {
            return null;
        }

        $fim = $this->concluido_em ?? now();

        return gmdate('H:i:s', (int) $this->iniciado_em->diffInSeconds($fim));
    }
}
