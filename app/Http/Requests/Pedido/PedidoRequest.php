<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

class PedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descricao'   => 'required|string|min:3|max:120',
            'nomecliente' => 'required|string|max:100',
            'produto'     => 'required|string|max:70',
            'preco'       => 'required|numeric|min:0.01',
            'quantidade'  => 'required|integer|min:1|max:9999',
        ];
    }

    public function messages(): array
    {
        return [
            'descricao.required'   => 'O campo Descrição é obrigatório.',
            'descricao.string'     => 'O campo Descrição deve ser um texto válido.',
            'descricao.min'        => 'O campo Descrição deve conter entre :min e :max caracteres.',
            'descricao.max'        => 'O campo Descrição não pode ter mais que :max caracteres.',
            'nomecliente.required' => 'O campo Nome do Cliente é obrigatório.',
            'nomecliente.max'      => 'O campo Nome do Cliente não pode ter mais que :max caracteres.',
            'produto.required'     => 'O campo Produto é obrigatório.',
            'produto.max'          => 'O campo Produto não pode ter mais que :max caracteres.',
            'preco.required'       => 'O campo Preço é obrigatório.',
            'preco.numeric'        => 'O campo Preço deve ser um valor numérico.',
            'preco.min'            => 'O campo Preço deve ser maior que zero.',
            'quantidade.required'  => 'O campo Quantidade é obrigatório.',
            'quantidade.integer'   => 'O campo Quantidade deve ser um número inteiro.',
            'quantidade.min'       => 'O campo Quantidade deve ser no mínimo :min.',
            'quantidade.max'       => 'O campo Quantidade não pode ser maior que :max.',
        ];
    }
}
