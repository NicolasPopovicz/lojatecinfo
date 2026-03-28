@extends('layouts.admin')

@section('titulo', 'Novo Pedido')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pedidos.index') }}">Pedidos</a></li>
    <li class="breadcrumb-item active">Novo</li>
@endsection

@section('conteudo')
<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title">Dados do Pedido</h3>
    </div>

    <form method="POST" action="{{ route('pedidos.store') }}" id="form-pedido">
        @csrf

        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="descricao">Descrição <span class="text-danger">*</span></label>
                        <input type="text" id="descricao" name="descricao"
                               class="form-control @error('descricao') is-invalid @enderror"
                               value="{{ old('descricao') }}" maxlength="120" required autofocus>
                        @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="nomecliente">Nome do Cliente <span class="text-danger">*</span></label>
                        <input type="text" id="nomecliente" name="nomecliente"
                               class="form-control @error('nomecliente') is-invalid @enderror"
                               value="{{ old('nomecliente') }}" maxlength="100" required>
                        @error('nomecliente') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="produto">Produto <span class="text-danger">*</span></label>
                        <input type="text" id="produto" name="produto"
                               class="form-control @error('produto') is-invalid @enderror"
                               value="{{ old('produto') }}" maxlength="70" required>
                        @error('produto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="preco-display">Preço <span class="text-danger">*</span></label>
                        {{-- Input de exibição: formatado como BRL, não é submetido --}}
                        <input type="text" id="preco-display" inputmode="numeric"
                               class="form-control @error('preco') is-invalid @enderror"
                               value=""
                               placeholder="R$ 0,00" autocomplete="off">
                        {{-- Hidden: valor numérico puro enviado ao servidor --}}
                        <input type="hidden" id="preco" name="preco"
                               value="{{ old('preco') }}">
                        @error('preco') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="quantidade">Quantidade <span class="text-danger">*</span></label>
                        <input type="number" id="quantidade" name="quantidade"
                               class="form-control @error('quantidade') is-invalid @enderror"
                               value="{{ old('quantidade') }}" min="1" max="9999" required>
                        @error('quantidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Total</label>
                        <div class="form-control bg-light text-right font-weight-bold" id="total-exibido">
                            R$ 0,00
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>Salvar
            </button>
            <a href="{{ route('pedidos.index') }}" class="btn btn-default ml-2">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@include('pedidos._script-preco', ['precoInicial' => old('preco', '')])
@endpush
