<?php

namespace App\DTO\Importacao;

use App\Http\Requests\Importacao\ImportacaoUploadRequest;
use Illuminate\Http\UploadedFile;

class ImportacaoUploadDTO
{
    public function __construct(
        public readonly UploadedFile $arquivo,
    ) {}

    public static function fromRequest(ImportacaoUploadRequest $request): self
    {
        return new self(
            arquivo: $request->file('arquivo'),
        );
    }
}
