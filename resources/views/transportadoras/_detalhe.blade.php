<dl class="row mb-0">
    <dt class="col-sm-4">Nome</dt>
    <dd class="col-sm-8">{{ $transportadora->nome }}</dd>

    <dt class="col-sm-4">CNPJ</dt>
    <dd class="col-sm-8">{{ $transportadora->cnpj }}</dd>

    <dt class="col-sm-4">Endereço</dt>
    <dd class="col-sm-8">
        {{ $transportadora->rua }}, {{ $transportadora->numero }}
        @if ($transportadora->complemento) — {{ $transportadora->complemento }} @endif
    </dd>

    <dt class="col-sm-4">Bairro</dt>
    <dd class="col-sm-8">{{ $transportadora->bairro }}</dd>

    <dt class="col-sm-4">Cidade / UF</dt>
    <dd class="col-sm-8">{{ $transportadora->cidade }} / {{ $transportadora->estado }}</dd>

    <dt class="col-sm-4">Cadastrado em</dt>
    <dd class="col-sm-8">{{ $transportadora->created_at?->format('d/m/Y H:i') }}</dd>

    <dt class="col-sm-4">Atualizado em</dt>
    <dd class="col-sm-8">{{ $transportadora->updated_at?->format('d/m/Y H:i') }}</dd>
</dl>
