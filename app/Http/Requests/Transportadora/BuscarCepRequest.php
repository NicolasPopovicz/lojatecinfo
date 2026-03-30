<?php

namespace App\Http\Requests\Transportadora;

use Illuminate\Foundation\Http\FormRequest;

class BuscarCepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cep' => 'required|string|regex:/^\d{5}-?\d{3}$/',
        ];
    }

    public function messages(): array
    {
        return [
            'cep.required' => 'Informe o CEP.',
            'cep.regex'    => 'CEP inválido. Use o formato 00000-000.',
        ];
    }
}
