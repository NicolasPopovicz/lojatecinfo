@extends('layouts.admin')

@section('titulo', 'Importação #' . $importacao->id)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pedidos.index') }}">Pedidos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pedidos.importar') }}">Importar CSV</a></li>
    <li class="breadcrumb-item active">Importação #{{ $importacao->id }}</li>
@endsection

@php
    $statusColor = match ($importacao->status) {
        \App\Enums\StatusImportacao::Concluido   => 'success',
        \App\Enums\StatusImportacao::Processando => 'primary',
        \App\Enums\StatusImportacao::Lendo       => 'info',
        \App\Enums\StatusImportacao::Pausada     => 'warning',
        \App\Enums\StatusImportacao::Pendente    => 'secondary',
        default                                  => 'danger',
    };
    $inseridas   = max(0, $importacao->linhas_processadas - $importacao->linhas_com_erro);
    $emAndamento = $importacao->status->estaEmAndamento();
    $preparando  = in_array($importacao->status, [
        \App\Enums\StatusImportacao::Pendente,
        \App\Enums\StatusImportacao::Lendo,
    ]);
@endphp

@section('conteudo')

<div class="card card-outline card-{{ $statusColor }}" id="card-progresso">

    {{-- Cabeçalho --}}
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-file-alt mr-2"></i>
            <strong>{{ $importacao->arquivo_original }}</strong>
        </h3>
        <div class="card-tools d-flex align-items-center">

            <span class="badge badge-{{ $statusColor }} mr-3 px-3 py-1" id="status-rotulo"
                  style="font-size:.8rem">
                {{ $importacao->status->rotulo() }}
            </span>

            <form id="form-pausar" method="POST"
                  action="{{ route('pedidos.importar.pausar', $importacao) }}"
                  data-no-loading
                  style="{{ $importacao->status->podePausar() ? '' : 'display:none' }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning mr-1">
                    <i class="fas fa-pause mr-1"></i>Pausar
                </button>
            </form>

            <form id="form-retomar" method="POST"
                  action="{{ route('pedidos.importar.retomar', $importacao) }}"
                  data-no-loading
                  style="{{ $importacao->status->podeRetomar() ? '' : 'display:none' }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-success mr-1">
                    <i class="fas fa-play mr-1"></i>Retomar
                </button>
            </form>

            <span id="form-cancelar" style="{{ $importacao->status->podeCancelar() ? '' : 'display:none' }}">
                <button type="button" class="btn btn-sm btn-danger"
                    onclick="Confirmar.abrir({
                        titulo: 'Cancelar Importação',
                        mensagem: 'Cancelar e remover esta importação? Os registros já inseridos na base permanecerão.',
                        action: '{{ route('pedidos.importar.cancelar', $importacao) }}',
                        method: 'DELETE',
                        labelConfirmar: 'Cancelar Importação',
                        loadingMsg: 'Cancelando...'
                    })">
                    <i class="fas fa-times mr-1"></i>Cancelar
                </button>
            </span>
        </div>
    </div>

    <div class="card-body">

        {{-- ── FASE PREPARANDO: Pendente ou Lendo ── --}}
        <div id="painel-preparando" style="{{ $preparando ? '' : 'display:none' }}">
            <div class="text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-circle-notch fa-spin fa-3x text-info"></i>
                </div>
                <h5 class="text-muted mb-1" id="msg-preparando">
                    @if ($importacao->status === \App\Enums\StatusImportacao::Pendente)
                        Aguardando um leitor disponível...
                    @else
                        Lendo e distribuindo o arquivo para processamento...
                    @endif
                </h5>
                <small class="text-muted">O processamento iniciará automaticamente em seguida.</small>
            </div>

            <div class="progress mb-3" style="height:10px; border-radius:6px">
                <div class="progress-bar progress-bar-striped progress-bar-animated"
                     style="width:100%; background-color:#17a2b8"></div>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning elevation-1">
                            <i class="fas fa-stopwatch"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Tempo decorrido</span>
                            <span class="info-box-number" id="duracao-prep">
                                {{ $importacao->duracao ?? '—' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info elevation-1">
                            <i class="fas fa-list-ol"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Linhas identificadas</span>
                            <span class="info-box-number" id="total-linhas-prep">
                                {{ $importacao->total_linhas > 0 ? number_format($importacao->total_linhas, 0, ',', '.') : '—' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger elevation-1">
                            <i class="fas fa-times-circle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Linhas com erro</span>
                            <span class="info-box-number" id="linhas-erro-prep">
                                {{ number_format($importacao->linhas_com_erro, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── FASE PROCESSANDO / CONCLUÍDO ── --}}
        <div id="painel-processando" style="{{ $preparando ? 'display:none' : '' }}">

            <div class="progress mb-4" style="height:28px; border-radius:6px">
                <div id="barra-progresso"
                     class="progress-bar progress-bar-striped
                            {{ $importacao->status === \App\Enums\StatusImportacao::Concluido ? 'bg-success' :
                               ($importacao->status === \App\Enums\StatusImportacao::Pausada  ? 'bg-warning' :
                               (in_array($importacao->status, [\App\Enums\StatusImportacao::Falhou, \App\Enums\StatusImportacao::Cancelada]) ? 'bg-danger' : 'bg-primary'))
                            }}
                            {{ $emAndamento && $importacao->status !== \App\Enums\StatusImportacao::Pausada ? 'progress-bar-animated' : '' }}"
                     role="progressbar"
                     style="width:{{ $importacao->percentual }}%; font-size:.95rem; font-weight:600"
                     aria-valuenow="{{ $importacao->percentual }}"
                     aria-valuemin="0" aria-valuemax="100">
                    <span id="percentual-texto">{{ $importacao->percentual }}%</span>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info elevation-1">
                            <i class="fas fa-list-ol"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total de Linhas</span>
                            <span class="info-box-number" id="total-linhas">
                                {{ number_format($importacao->total_linhas, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success elevation-1">
                            <i class="fas fa-check-circle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Importadas</span>
                            <span class="info-box-number text-success" id="linhas-ok">
                                {{ number_format($inseridas, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger elevation-1">
                            <i class="fas fa-times-circle"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Erros</span>
                            <span class="info-box-number {{ $importacao->linhas_com_erro > 0 ? 'text-danger' : '' }}"
                                  id="linhas-erro">
                                {{ number_format($importacao->linhas_com_erro, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning elevation-1">
                            <i class="fas fa-stopwatch"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Duração</span>
                            <span class="info-box-number" id="duracao">
                                {{ $importacao->duracao ?? '—' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Metadados --}}
        <div class="row mt-2">
            <div class="col-md-6">
                <small class="text-muted">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    <strong>Iniciado em:</strong>
                    {{ $importacao->iniciado_em?->format('d/m/Y \à\s H:i:s') ?? '—' }}
                </small>
            </div>
            <div class="col-md-6">
                <small class="text-muted">
                    <i class="fas fa-flag-checkered mr-1"></i>
                    <strong>Concluído em:</strong>
                    <span id="concluido-em">
                        {{ $importacao->concluido_em?->format('d/m/Y \à\s H:i:s') ?? '—' }}
                    </span>
                </small>
            </div>
        </div>

    </div>

    {{-- Rodapé pós-conclusão --}}
    <div class="card-footer" id="painel-concluido"
         style="{{ $emAndamento ? 'display:none' : '' }}">
        <a href="{{ route('pedidos.index') }}" class="btn btn-success">
            <i class="fas fa-list mr-1"></i>Ver Pedidos Importados
        </a>
        <a href="{{ route('pedidos.importar') }}" class="btn btn-primary ml-2">
            <i class="fas fa-upload mr-1"></i>Nova Importação
        </a>
        <a href="{{ route('pedidos.importacoes') }}" class="btn btn-default ml-2">
            <i class="fas fa-history mr-1"></i>Histórico
        </a>
        @if (!$emAndamento && $importacao->linhas_com_erro > 0)
        <a href="{{ route('pedidos.importar.erros', $importacao) }}"
           class="btn btn-warning ml-2" id="btn-ver-erros">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Ver erros de validação
            ({{ number_format($importacao->linhas_com_erro, 0, ',', '.') }})
        </a>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const urlProgresso = '{{ route('pedidos.importar.progresso', $importacao) }}';
    const jaFinalizado = {{ $importacao->status->estaEmAndamento() ? 'false' : 'true' }};

    if (jaFinalizado) return;

    const barra        = document.getElementById('barra-progresso');
    const painelPrep   = document.getElementById('painel-preparando');
    const painelProc   = document.getElementById('painel-processando');
    const formPausar   = document.getElementById('form-pausar');
    const formRetomar  = document.getElementById('form-retomar');
    const formCancelar = document.getElementById('form-cancelar');

    const source = new EventSource(urlProgresso);

    source.onmessage = function (e) {
        const data = JSON.parse(e.data);
        const ok   = data.linhas_processadas - data.linhas_com_erro;
        const prep = data.status === 'pendente' || data.status === 'lendo';

        painelPrep.style.display = prep ? '' : 'none';
        painelProc.style.display = prep ? 'none' : '';

        document.getElementById('status-rotulo').textContent = data.status_rotulo;
        document.getElementById('concluido-em').textContent  = data.concluido_em ?? '—';

        if (prep) {
            document.getElementById('duracao-prep').textContent = data.duracao ?? '—';
            document.getElementById('msg-preparando').textContent = data.status === 'pendente'
                ? 'Aguardando um leitor disponível...'
                : 'Lendo e distribuindo o arquivo para processamento...';
            document.getElementById('total-linhas-prep').textContent = data.total_linhas > 0
                ? fmt(data.total_linhas) : '—';
            document.getElementById('linhas-erro-prep').textContent = fmt(data.linhas_com_erro);
        } else {
            document.getElementById('total-linhas').textContent     = fmt(data.total_linhas);
            document.getElementById('linhas-ok').textContent        = fmt(ok);
            document.getElementById('linhas-erro').textContent      = fmt(data.linhas_com_erro);
            document.getElementById('duracao').textContent          = data.duracao ?? '—';
            document.getElementById('percentual-texto').textContent = data.percentual + '%';

            barra.style.width = data.percentual + '%';
            barra.setAttribute('aria-valuenow', data.percentual);
            barra.classList.remove('bg-primary', 'bg-warning', 'bg-success', 'bg-danger');

            if (data.status === 'pausada') {
                barra.classList.add('bg-warning');
                barra.classList.remove('progress-bar-animated');
            } else if (data.status === 'falhou' || data.status === 'cancelada') {
                barra.classList.add('bg-danger');
                barra.classList.remove('progress-bar-animated');
            } else if (data.concluido) {
                barra.classList.add('bg-success');
                barra.classList.remove('progress-bar-animated');
            } else {
                barra.classList.add('bg-primary', 'progress-bar-animated');
            }
        }

        formPausar.style.display   = data.pode_pausar   ? '' : 'none';
        formRetomar.style.display  = data.pode_retomar  ? '' : 'none';
        formCancelar.style.display = data.pode_cancelar ? '' : 'none';

        if (data.concluido) {
            source.close();
            document.getElementById('painel-concluido').style.display = '';

            if (data.linhas_com_erro > 0) {
                const btn = document.getElementById('btn-ver-erros');
                if (btn) {
                    btn.style.display = '';
                    btn.querySelector('span') && (btn.innerHTML =
                        `<i class="fas fa-exclamation-triangle mr-1"></i>Ver erros de validação (${fmt(data.linhas_com_erro)})`
                    );
                }
            }
        }
    };

    source.onerror = function () {
        if (jaFinalizado) source.close();
    };

    function fmt(n) {
        return Number(n).toLocaleString('pt-BR');
    }
})();
</script>
@endpush
