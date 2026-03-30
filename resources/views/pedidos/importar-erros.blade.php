@extends('layouts.admin')

@section('titulo', 'Erros — Importação #' . $importacao->id)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pedidos.index') }}">Pedidos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pedidos.importacoes') }}">Histórico</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('pedidos.importar.acompanhar', $importacao) }}">Importação #{{ $importacao->id }}</a>
    </li>
    <li class="breadcrumb-item active">Erros de validação</li>
@endsection

@section('conteudo')

<div class="card card-outline card-warning">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>{{ $importacao->arquivo_original }}</strong>
            <span class="badge badge-warning ml-2">
                {{ number_format($importacao->linhas_com_erro, 0, ',', '.') }} linha(s) rejeitada(s)
            </span>
        </h3>
        <div class="card-tools">
            @if ($temArquivoErros)
            <a href="{{ route('pedidos.importar.exportar-erros', $importacao) }}"
               class="btn btn-sm btn-warning">
                <i class="fas fa-download mr-1"></i>Baixar CSV com erros
            </a>
            @endif
        </div>
    </div>

    <div class="card-body p-0">

        @if (empty($errosAgrupados))
            <div class="p-4 text-muted text-center">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <p>Nenhum detalhe de erro disponível.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:130px">Parâmetro</th>
                            <th class="text-right" style="width:160px">Linhas rejeitadas</th>
                            <th></th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($errosAgrupados as $i => $grupo)
                        {{-- Linha do parâmetro (clicável) --}}
                        <tr class="erro-header" data-toggle="collapse"
                            data-target="#detalhe-{{ $i }}" aria-expanded="false">
                            <td>
                                <span class="badge badge-secondary" style="font-size:.85rem">
                                    {{ $grupo['parametro'] }}
                                </span>
                            </td>
                            <td class="text-right font-weight-bold text-danger">
                                {{ number_format($grupo['linhas'], 0, ',', '.') }}
                            </td>
                            <td class="text-muted small">
                                {{ count($grupo['erros']) }} tipo(s) de erro
                            </td>
                            <td class="text-center">
                                <i class="fas fa-chevron-down chevron-icon" style="transition: transform .25s ease"></i>
                            </td>
                        </tr>
                        {{-- Linha sempre presente; o collapse fica no div interno --}}
                        <tr class="erro-detalhe">
                            <td colspan="4" class="p-0 border-top-0">
                                <div id="detalhe-{{ $i }}" class="collapse">
                                    <table class="table mb-0" style="background:#f8f9fa">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="padding-left:2rem">Motivo do erro</th>
                                                <th style="width:220px">Exemplo</th>
                                                <th class="text-right" style="width:130px">Ocorrências</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($grupo['erros'] as $erro)
                                            <tr>
                                                <td style="padding-left:2rem">{{ $erro['descricao'] }}</td>
                                                <td>
                                                    @if ($erro['exemplo'] !== '')
                                                        <span class="d-inline-block text-truncate text-muted"
                                                              style="max-width:200px; font-size:.8rem; font-family:monospace; vertical-align:middle"
                                                              title="{{ $erro['exemplo'] }}">{{ $erro['exemplo'] }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-right font-weight-bold">
                                                    {{ number_format($erro['total'], 0, ',', '.') }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="thead-light">
                        <tr>
                            <th colspan="3" class="text-right">Total de linhas rejeitadas</th>
                            <th class="text-right text-danger">
                                {{ number_format($importacao->linhas_com_erro, 0, ',', '.') }}
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    @if ($temArquivoErros)
    <div class="card-footer bg-light">
        <i class="fas fa-info-circle text-info mr-1"></i>
        Baixe o CSV, corrija os campos destacados acima e
        <a href="{{ route('pedidos.importar') }}">reimporte o arquivo</a>.
        O separador deve ser <strong>ponto e vírgula (;)</strong> e a coluna
        <code>motivo_erro</code> pode ser removida antes de reimportar.
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
$('.erro-header').each(function () {
    const $trigger = $(this);
    const $target  = $($trigger.data('target'));
    const $chevron = $trigger.find('.chevron-icon');

    $target.on('show.bs.collapse', function () {
        $chevron.css('transform', 'rotate(180deg)');
        $trigger.addClass('expanded');
    });
    $target.on('hide.bs.collapse', function () {
        $chevron.css('transform', 'rotate(0deg)');
        $trigger.removeClass('expanded');
    });
});
</script>
@endpush

@push('styles')
<style>
.erro-header {
    cursor: pointer;
    background-color: #fff8e1;
    transition: background-color .15s ease;
}
.erro-header:hover,
.erro-header.expanded {
    background-color: #ffecb3;
}
.erro-detalhe > td {
    padding: 0 !important;
    border-top: none !important;
}
</style>
@endpush
