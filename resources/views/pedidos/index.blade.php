@extends('layouts.admin')

@section('titulo', 'Pedidos')

@section('breadcrumb')
    <li class="breadcrumb-item active">Pedidos</li>
@endsection

@section('conteudo')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">

            <form method="GET" action="{{ route('pedidos.index') }}" class="d-flex align-items-center" data-no-loading>
                <div class="input-group input-group-sm mr-2" style="width:260px">
                    <input type="text" name="busca" class="form-control"
                           placeholder="Buscar por cliente..."
                           value="{{ $busca }}">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-default">
                            <i class="fas fa-search"></i>
                        </button>
                        @if ($busca)
                            <a href="{{ route('pedidos.index', ['por_pagina' => $porPagina]) }}"
                               class="btn btn-default" title="Limpar busca">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="input-group input-group-sm" style="width:130px">
                    <select name="por_pagina" class="form-control" onchange="this.form.submit()"
                            title="Itens por página">
                        @foreach (\App\Services\PedidoService::OPCOES_POR_PAGINA as $opcao)
                            <option value="{{ $opcao }}" {{ $porPagina === $opcao ? 'selected' : '' }}>
                                {{ $opcao }} por pág.
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <div class="d-flex gap-2">
                <a href="{{ route('pedidos.importar') }}" class="btn btn-info btn-sm mr-2">
                    <i class="fas fa-upload mr-1"></i>Importar CSV
                </a>
                <a href="{{ route('pedidos.exportar-csv', ['busca' => $busca]) }}"
                   class="btn btn-success btn-sm mr-2"
                   data-loading="Gerando CSV..." data-loading-auto>
                    <i class="fas fa-download mr-1"></i>Exportar CSV
                </a>
                <a href="{{ route('pedidos.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Novo Pedido
                </a>
            </div>
        </div>
    </div>

    <div class="card-body table-responsive p-0">
        <table class="table table-hover table-striped text-nowrap">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Produto</th>
                    <th>Descrição</th>
                    <th class="text-right">Preço</th>
                    <th class="text-right">Qtd</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pedidos as $pedido)
                    <tr>
                        <td>{{ $pedido->id }}</td>
                        <td>{{ $pedido->nomecliente }}</td>
                        <td>{{ $pedido->produto }}</td>
                        <td>{{ Str::limit($pedido->descricao, 50) }}</td>
                        <td class="text-right">R$ {{ number_format($pedido->preco, 2, ',', '.') }}</td>
                        <td class="text-right">{{ $pedido->quantidade }}</td>
                        <td class="text-right">R$ {{ number_format($pedido->total, 2, ',', '.') }}</td>
                        <td class="text-right">
                            <a href="{{ route('pedidos.show', $pedido) }}"
                               class="btn btn-xs btn-info" title="Detalhes">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('pedidos.edit', $pedido) }}"
                               class="btn btn-xs btn-warning" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="{{ route('pedidos.destroy', $pedido) }}"
                                  style="display:inline"
                                  onsubmit="return confirm('Excluir este pedido?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-danger" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">Nenhum pedido encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($pedidos->hasPages())
        <div class="card-footer">
            {{ $pedidos->links() }}
        </div>
    @endif
</div>
@endsection
