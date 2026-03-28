@extends('layouts.admin')

@section('titulo', 'Transportadoras')

@section('breadcrumb')
    <li class="breadcrumb-item active">Transportadoras</li>
@endsection

@section('conteudo')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">

            <form method="GET" action="{{ route('transportadoras.index') }}" class="d-flex align-items-center" data-no-loading>
                <div class="input-group input-group-sm mr-2" style="width:260px">
                    <input type="text" name="busca" class="form-control"
                           placeholder="Buscar por nome ou CNPJ..."
                           value="{{ $busca }}">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-default">
                            <i class="fas fa-search"></i>
                        </button>
                        @if ($busca)
                            <a href="{{ route('transportadoras.index', ['por_pagina' => $porPagina]) }}"
                               class="btn btn-default" title="Limpar busca">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="input-group input-group-sm" style="width:130px">
                    <select name="por_pagina" class="form-control" onchange="this.form.submit()"
                            title="Itens por página">
                        @foreach (\App\Services\TransportadoraService::OPCOES_POR_PAGINA as $opcao)
                            <option value="{{ $opcao }}" {{ $porPagina === $opcao ? 'selected' : '' }}>
                                {{ $opcao }} por pág.
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <a href="{{ route('transportadoras.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i>Nova Transportadora
            </a>
        </div>
    </div>

    <div class="card-body table-responsive p-0">
        <table class="table table-hover table-striped text-nowrap">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>CNPJ</th>
                    <th>Cidade / UF</th>
                    <th>Endereço</th>
                    <th class="text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transportadoras as $t)
                    <tr>
                        <td>{{ $t->id }}</td>
                        <td>{{ $t->nome }}</td>
                        <td>{{ $t->cnpj }}</td>
                        <td>{{ $t->cidade }}/{{ $t->estado }}</td>
                        <td>{{ $t->rua }}, {{ $t->numero }}
                            @if ($t->complemento) — {{ $t->complemento }} @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('transportadoras.show', $t) }}"
                               class="btn btn-xs btn-info" title="Detalhes">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('transportadoras.edit', $t) }}"
                               class="btn btn-xs btn-warning" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="{{ route('transportadoras.destroy', $t) }}"
                                  style="display:inline"
                                  onsubmit="return confirm('Excluir {{ addslashes($t->nome) }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-danger" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">Nenhuma transportadora encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($transportadoras->hasPages())
        <div class="card-footer">
            {{ $transportadoras->links() }}
        </div>
    @endif
</div>
@endsection
