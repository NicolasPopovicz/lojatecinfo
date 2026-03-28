<?php

namespace App\Http\Requests\Transportadora;

use Illuminate\Foundation\Http\FormRequest;

class TransportadoraRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome'        => 'required|string|min:3|max:100',
            'cnpj'        => 'required|string|size:14|regex:/^\d+$/',
            'bairro'      => 'required|string|max:150',
            'cidade'      => 'required|string|max:80',
            'estado'      => 'required|string|max:2',
            'rua'         => 'required|string|max:150|',
            'numero'      => 'required|string|max:10|regex:/^\d+$/',
            'complemento' => 'nullable|string|max:100'
        ];
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        return [
            'nome.string'     => 'O campo Nome deve ser um texto válido.',
            'nome.min'        => 'O campo Nome deve conter entre :min e :max caracteres.',
            'nome.max'        => 'O campo Nome não pode ter mais que :max caracteres.',
            'nome.required'   => 'O campo Nome é obrigatório.',
            'cnpj.size'       => 'O campo CNPJ deve conter :size dígitos.',
            'cnpj.regex'      => 'O campo CNPJ informado não é válido.',
            'cnpj.required'   => 'O campo CNPJ é obrigatório.',
            'rua.max'         => 'O campo Rua não pode ter mais que :max caracteres.',
            'rua.required'    => 'O campo Rua é obrigatório.',
            'complemento.max' => 'O campo Complemento não pode ter mais que :max caracteres.',
            'numero.max'      => 'O campo Número não pode ter mais que :max dígitos.',
            'numero.regex'    => 'O campo Número informado não é válido.',
            'numero.required' => 'O campo Número é obrigatório.',
            'cidade.max'      => 'O campo Cidade não pode ter mais que :max dígitos.',
            'cidade.required' => 'O campo Cidade é obrigatório.',
            'estado.max'      => 'O campo Estado (UF) deve conter :size caracteres.',
            'estado.required' => 'O campo Estado (UF) é obrigatório.',
            'bairro.max'      => 'O campo Bairro deve conter :size caracteres.',
            'bairro.required' => 'O campo Bairro é obrigatório.',
        ];
    }
}