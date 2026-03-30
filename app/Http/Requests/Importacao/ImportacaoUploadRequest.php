<?php

namespace App\Http\Requests\Importacao;

use Illuminate\Foundation\Http\FormRequest;

class ImportacaoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'arquivo' => 'required|file|mimes:csv,txt|max:1048576',
        ];
    }

    public function messages(): array
    {
        return [
            'arquivo.required' => 'Selecione um arquivo CSV para importar.',
            'arquivo.file'     => 'O arquivo enviado é inválido.',
            'arquivo.mimes'    => 'O arquivo deve estar no formato CSV ou TXT.',
            'arquivo.max'      => 'O arquivo não pode ultrapassar ' . ini_get('upload_max_filesize') . '.',
        ];
    }
}
