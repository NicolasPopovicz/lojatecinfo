<?php

namespace App\Services;

use App\DTO\Transportadora\TransportadoraDTO;
use App\External\ViaCep;
use App\Models\Transportadora;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransportadoraService
{
    public const OPCOES_POR_PAGINA = [15, 25, 50, 100, 500];
    public const POR_PAGINA_PADRAO = 15;

    public function __construct(
        private readonly Transportadora $transportadora,
        private readonly ViaCep $viaCep,
    ) {}

    public function listar(?string $busca = null, int $porPagina = self::POR_PAGINA_PADRAO): LengthAwarePaginator
    {
        return $this->transportadora
            ->when($busca, fn ($q) => $q->where('nome', 'ilike', "%{$busca}%")
                                        ->orWhere('cnpj', 'like', "%{$busca}%"))
            ->orderBy('id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function buscarPorId(int $id): Transportadora
    {
        return $this->transportadora->findOrFail($id);
    }

    public function criar(TransportadoraDTO $dto): Transportadora
    {
        return $this->transportadora->create([
            'nome'        => $dto->nome,
            'cnpj'        => $dto->cnpj,
            'rua'         => $dto->rua,
            'numero'      => $dto->numero,
            'complemento' => $dto->complemento,
            'bairro'      => $dto->bairro,
            'cidade'      => $dto->cidade,
            'estado'      => $dto->estado,
        ]);
    }

    public function atualizar(Transportadora $transportadora, TransportadoraDTO $dto): Transportadora
    {
        $transportadora->update([
            'nome'        => $dto->nome,
            'cnpj'        => $dto->cnpj,
            'rua'         => $dto->rua,
            'numero'      => $dto->numero,
            'complemento' => $dto->complemento,
            'bairro'      => $dto->bairro,
            'cidade'      => $dto->cidade,
            'estado'      => $dto->estado,
        ]);

        return $transportadora->fresh();
    }

    public function deletar(Transportadora $transportadora): void
    {
        $transportadora->delete();
    }

    /**
     * Consulta o ViaCEP e retorna os campos de endereço mapeados.
     */
    public function buscarEnderecoPorCep(string $cep): ?array
    {
        return $this->viaCep->buscarPorCep($cep);
    }
}
