<?php

namespace App\Http\Requests\Transportadora;

use App\Services\TransportadoraService;
use Illuminate\Foundation\Http\FormRequest;

class TransportadoraListagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $opcoes = implode(',', TransportadoraService::OPCOES_POR_PAGINA);

        return [
            'busca'      => 'nullable|string|max:255',
            'por_pagina' => "nullable|integer|in:{$opcoes}",
        ];
    }
}
