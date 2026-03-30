@extends('layouts.admin')

@section('titulo', 'Histórico de Importações')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pedidos.index') }}">Pedidos</a></li>
    <li class="breadcrumb-item active">Histórico de Importações</li>
@endsection

@section('conteudo')

{{-- Filtros --}}
<div class="card card-outline card-primary">
    <div class="card-body">
        <form method="GET" action="{{ route('pedidos.importacoes') }}" data-no-loading>
            <div class="row align-items-end">
                <div class="col-md-5">
                    <label for="busca" class="mb-1">Buscar por arquivo</label>
                    <input type="text" id="busca" name="busca" value="{{ $busca }}"
                           class="form-control" placeholder="Nome do arquivo...">
                </div>
                <div class="col-md-3">
                    <label for="por_pagina" class="mb-1">Registros por página</label>
                    <select id="por_pagina" name="por_pagina" class="form-control"
                            onchange="this.form.submit()">
                        @foreach (\App\Services\ImportacaoService::OPCOES_POR_PAGINA as $opcao)
                            <option value="{{ $opcao }}" @selected($opcao === $porPagina)>
                                {{ $opcao }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2 mt-2 mt-md-0">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search mr-1"></i>Buscar
                    </button>
                    @if ($busca)
                        <a href="{{ route('pedidos.importacoes') }}" class="btn btn-default ml-2">
                            <i class="fas fa-times mr-1"></i>Limpar
                        </a>
                    @endif
                    <a href="{{ route('pedidos.importar') }}" class="btn btn-info ml-auto">
                        <i class="fas fa-upload mr-1"></i>Nova Importação
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Tabela --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            {{ number_format($importacoes->total(), 0, ',', '.') }} importação(ões) encontrada(s)
        </h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover table-striped">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Arquivo</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Inseridas</th>
                    <th class="text-right">Erros</th>
                    <th style="width:110px">Status</th>
                    <th>Iniciado em</th>
                    <th>Concluído em</th>
                    <th style="width:80px">Duração</th>
                    <th style="width:140px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($importacoes as $item)
                @php
                    [$badgeClass, $badgeIcon] = match ($item->status) {
                        \App\Enums\StatusImportacao::Concluido   => ['badge-success', 'fa-check'],
                        \App\Enums\StatusImportacao::Processando => ['badge-primary',  'fa-sync fa-spin'],
                        \App\Enums\StatusImportacao::Lendo       => ['badge-info',     'fa-spinner fa-spin'],
                        \App\Enums\StatusImportacao::Pausada     => ['badge-warning',  'fa-pause'],
                        \App\Enums\StatusImportacao::Pendente    => ['badge-secondary','fa-clock'],
                        \App\Enums\StatusImportacao::Cancelada   => ['badge-dark',     'fa-ban'],
                        \App\Enums\StatusImportacao::Falhou      => ['badge-danger',   'fa-times'],
                    };
                    $inseridas = max(0, $item->linhas_processadas - $item->linhas_com_erro);
                @endphp
                <tr id="row-importacao-{{ $item->id }}">
                    <td>{{ $item->id }}</td>
                    <td>
                        <span title="{{ $item->arquivo_original }}">
                            {{ Str::limit($item->arquivo_original, 35) }}
                        </span>
                    </td>
                    <td class="text-right">
                        {{ $item->total_linhas > 0 ? number_format($item->total_linhas, 0, ',', '.') : '—' }}
                    </td>
                    <td class="text-right {{ $inseridas > 0 ? 'text-success font-weight-bold' : '' }}">
                        <span class="cel-inseridas">
                            {{ $item->total_linhas > 0 ? number_format($inseridas, 0, ',', '.') : '—' }}
                        </span>
                    </td>
                    <td class="text-right {{ $item->linhas_com_erro > 0 ? 'text-danger' : '' }}">
                        <span class="cel-erros">
                            {{ $item->total_linhas > 0 ? number_format($item->linhas_com_erro, 0, ',', '.') : '—' }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $badgeClass }}">
                            <i class="fas {{ $badgeIcon }} mr-1"></i>
                            <span class="cel-status-rotulo">{{ $item->status->rotulo() }}</span>
                        </span>
                        @if ($item->status->estaEmAndamento() && $item->total_linhas > 0)
                            <div class="progress mt-1" style="height:4px">
                                <div class="progress-bar bg-info cel-barra" style="width:{{ $item->percentual }}%"></div>
                            </div>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        {{ $item->iniciado_em?->format('d/m/Y H:i:s') ?? '—' }}
                    </td>
                    <td class="text-nowrap">
                        {{ $item->concluido_em?->format('d/m/Y H:i:s') ?? '—' }}
                    </td>
                    <td class="text-nowrap cel-duracao">{{ $item->duracao ?? '—' }}</td>
                    <td>
                        @if ($item->status->estaEmAndamento())
                            <a href="{{ route('pedidos.importar.acompanhar', $item) }}"
                               class="btn btn-sm btn-info">
                                <i class="fas fa-eye mr-1"></i>Acompanhar
                            </a>
                        @elseif ($item->status === \App\Enums\StatusImportacao::Concluido && $item->erros_resumo)
                            <a href="{{ route('pedidos.importar.erros', $item) }}"
                               class="btn btn-sm btn-warning">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Ver Erros
                            </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        Nenhuma importação encontrada.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($importacoes->hasPages())
        <div class="card-footer">
            {{ $importacoes->links() }}
        </div>
    @endif
</div>

@endsection

@push('scripts')
@if ($importacoes->contains(fn($i) => $i->status->estaEmAndamento()))
<script>
(function () {
    const ids = [
        @foreach ($importacoes->filter(fn($i) => $i->status->estaEmAndamento()) as $item)
            {{ $item->id }},
        @endforeach
    ];
    const urlHistorico = '{{ route('pedidos.importar.historico') }}?ids=' + ids.join(',');

    const intervalo = setInterval(async () => {
        try {
            const res  = await fetch(urlHistorico, { headers: { Accept: 'application/json' } });
            const data = await res.json();

            if (!data.tem_andamento) {
                clearInterval(intervalo);
            }

            // Atualiza cada linha em andamento sem recarregar a página inteira
            data.itens.forEach(item => {
                const row = document.getElementById(`row-importacao-${item.id}`);
                if (!row) return;

                row.querySelector('.cel-inseridas').textContent = item.importadas_fmt;
                row.querySelector('.cel-erros').textContent     = item.erros_fmt;
                row.querySelector('.cel-duracao').textContent   = item.duracao;
                row.querySelector('.cel-status-rotulo').textContent = item.status_rotulo;

                const barra = row.querySelector('.cel-barra');
                if (barra) barra.style.width = item.percentual + '%';
            });
        } catch (e) {}
    }, 2000);
})();
</script>
@endif
@endpush
