<?php

namespace App\Http\Requests\Importacao;

use Illuminate\Foundation\Http\FormRequest;

class ImportacaoHistoricoJsonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'nullable|string',
        ];
    }
}
