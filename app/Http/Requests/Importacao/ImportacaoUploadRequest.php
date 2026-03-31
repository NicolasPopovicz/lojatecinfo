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
            'arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:' . $this->maxKb()],
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

    /** Converte upload_max_filesize do php.ini para KB (unidade que o Laravel espera). */
    private function maxKb(): int
    {
        $raw  = ini_get('upload_max_filesize');
        $unit = strtoupper(substr($raw, -1));
        $val  = (int) $raw;

        return match ($unit) {
            'G'     => $val * 1024 * 1024,
            'M'     => $val * 1024,
            'K'     => $val,
            default => (int) ceil($val / 1024),
        };
    }
}
