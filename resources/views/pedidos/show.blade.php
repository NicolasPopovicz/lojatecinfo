@extends('layouts.admin')

@section('titulo', 'Pedido #' . $pedido->id)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pedidos.index') }}">Pedidos</a></li>
    <li class="breadcrumb-item active">#{{ $pedido->id }}</li>
@endsection

@section('conteudo')
<div class="card card-info">
    <div class="card-header">
        <h3 class="card-title">Pedido #{{ $pedido->id }}</h3>
    </div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">Descrição</dt>
            <dd class="col-sm-9">{{ $pedido->descricao }}</dd>

            <dt class="col-sm-3">Cliente</dt>
            <dd class="col-sm-9">{{ $pedido->nomecliente }}</dd>

            <dt class="col-sm-3">Produto</dt>
            <dd class="col-sm-9">{{ $pedido->produto }}</dd>

            <dt class="col-sm-3">Preço</dt>
            <dd class="col-sm-9">R$ {{ number_format($pedido->preco, 2, ',', '.') }}</dd>

            <dt class="col-sm-3">Quantidade</dt>
            <dd class="col-sm-9">{{ $pedido->quantidade }}</dd>

            <dt class="col-sm-3">Total</dt>
            <dd class="col-sm-9">
                <strong class="text-success">R$ {{ number_format($pedido->total, 2, ',', '.') }}</strong>
            </dd>

            <dt class="col-sm-3">Criado em</dt>
            <dd class="col-sm-9">{{ $pedido->created_at?->format('d/m/Y H:i') }}</dd>

            <dt class="col-sm-3">Atualizado em</dt>
            <dd class="col-sm-9">{{ $pedido->updated_at?->format('d/m/Y H:i') }}</dd>
        </dl>
    </div>
    <div class="card-footer">
        <a href="{{ route('pedidos.edit', $pedido) }}" class="btn btn-warning">
            <i class="fas fa-edit mr-1"></i>Editar
        </a>
        <a href="{{ route('pedidos.index') }}" class="btn btn-default ml-2">
            <i class="fas fa-arrow-left mr-1"></i>Voltar
        </a>
    </div>
</div>
@endsection
