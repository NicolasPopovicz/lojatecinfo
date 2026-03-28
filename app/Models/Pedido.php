<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['descricao', 'nomecliente', 'produto', 'preco', 'quantidade', 'total'])]
class Pedido extends Model
{
    protected function casts(): array
    {
        return [
            'preco'      => 'decimal:2',
            'total'      => 'decimal:2',
            'quantidade' => 'integer',
        ];
    }
}
