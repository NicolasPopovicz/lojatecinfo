<?php

namespace App\External;

use Illuminate\Support\Facades\Http;
use Throwable;
use Exception;

class ViaCep
{
    /**
     * Busca endereço pelo CEP (somente dígitos ou com hífen).
     * Retorna array com os campos mapeados, ou null em caso de erro / CEP não encontrado.
     */
    public function buscarPorCep(string $cep): ?array
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            return null;
        }

        try {
            $resposta = Http::timeout(10)->get("https://viacep.com.br/ws/{$cep}/json/");

            if ($resposta->failed()) {
                return null;
            }

            $dados = $resposta->json();

            // ViaCEP retorna {"erro": true} quando o CEP não existe
            if (!empty($dados['erro'])) {
                return null;
            }

            return [
                'rua'         => $dados['logradouro'] ?? '',
                'bairro'      => $dados['bairro']     ?? '',
                'cidade'      => $dados['localidade']  ?? '',
                'estado'      => $dados['uf']          ?? '',
                'cep'         => $dados['cep']         ?? '',
            ];
        } catch (Throwable $e) {
            throw new Exception('Erro ao consultar ViaCEP: ' . $e->getMessage(), 500);
        }
    }
}
