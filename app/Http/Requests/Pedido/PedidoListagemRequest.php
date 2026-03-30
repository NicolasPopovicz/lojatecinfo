<?php

namespace App\Http\Requests\Pedido;

use App\Services\PedidoService;
use Illuminate\Foundation\Http\FormRequest;

class PedidoListagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $opcoes = implode(',', PedidoService::OPCOES_POR_PAGINA);

        return [
            'busca'      => 'nullable|string|max:255',
            'por_pagina' => "nullable|integer|in:{$opcoes}",
        ];
    }
}
