@extends('layouts.admin')

@section('titulo', 'Transportadora #' . $transportadora->id)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transportadoras.index') }}">Transportadoras</a></li>
    <li class="breadcrumb-item active">#{{ $transportadora->id }}</li>
@endsection

@section('conteudo')
<div class="card card-info">
    <div class="card-header">
        <h3 class="card-title">{{ $transportadora->nome }}</h3>
    </div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">Nome</dt>
            <dd class="col-sm-9">{{ $transportadora->nome }}</dd>

            <dt class="col-sm-3">CNPJ</dt>
            <dd class="col-sm-9">{{ $transportadora->cnpj }}</dd>

            <dt class="col-sm-3">Rua</dt>
            <dd class="col-sm-9">{{ $transportadora->rua }}, {{ $transportadora->numero }}
                @if ($transportadora->complemento)
                    — {{ $transportadora->complemento }}
                @endif
            </dd>

            <dt class="col-sm-3">Bairro</dt>
            <dd class="col-sm-9">{{ $transportadora->bairro }}</dd>

            <dt class="col-sm-3">Cidade / UF</dt>
            <dd class="col-sm-9">{{ $transportadora->cidade }} / {{ $transportadora->estado }}</dd>

            <dt class="col-sm-3">Cadastrado em</dt>
            <dd class="col-sm-9">{{ $transportadora->created_at?->format('d/m/Y H:i') }}</dd>

            <dt class="col-sm-3">Atualizado em</dt>
            <dd class="col-sm-9">{{ $transportadora->updated_at?->format('d/m/Y H:i') }}</dd>
        </dl>
    </div>
    <div class="card-footer">
        <a href="{{ route('transportadoras.edit', $transportadora) }}" class="btn btn-warning">
            <i class="fas fa-edit mr-1"></i>Editar
        </a>
        <a href="{{ route('transportadoras.index') }}" class="btn btn-default ml-2">
            <i class="fas fa-arrow-left mr-1"></i>Voltar
        </a>
    </div>
</div>
@endsection
