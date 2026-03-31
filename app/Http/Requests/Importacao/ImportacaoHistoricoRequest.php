<?php

namespace App\Http\Requests\Importacao;

use App\Services\ImportacaoService;
use Illuminate\Foundation\Http\FormRequest;

class ImportacaoHistoricoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $opcoes = implode(',', ImportacaoService::OPCOES_POR_PAGINA);

        return [
            'busca'      => 'nullable|string|max:255',
            'por_pagina' => "nullable|integer|in:{$opcoes}",
        ];
    }
}
