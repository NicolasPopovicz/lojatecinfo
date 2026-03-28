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
    $inseridas = max(0, $importacao->linhas_processadas - $importacao->linhas_com_erro);
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

            <form id="form-cancelar" method="POST"
                  action="{{ route('pedidos.importar.cancelar', $importacao) }}"
                  data-no-loading
                  onsubmit="return confirm('Cancelar e remover esta importação?\nOs registros já inseridos na base permanecerão.')"
                  style="{{ $importacao->status->podeCancelar() ? '' : 'display:none' }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fas fa-times mr-1"></i>Cancelar
                </button>
            </form>
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

            {{-- Barra indeterminada durante leitura --}}
            <div class="progress mb-3" style="height:10px; border-radius:6px">
                <div class="progress-bar progress-bar-striped progress-bar-animated"
                     style="width:100%; background-color:#17a2b8"></div>
            </div>

            {{-- Só mostra o que é confiável nessa fase --}}
            <div class="row justify-content-center">
                <div class="col-md-4 col-6">
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
                @if ($importacao->total_linhas > 0)
                <div class="col-md-4 col-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-info elevation-1">
                            <i class="fas fa-list-ol"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Linhas identificadas</span>
                            <span class="info-box-number" id="total-linhas-prep">
                                {{ number_format($importacao->total_linhas, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- ── FASE PROCESSANDO / CONCLUÍDO ── --}}
        <div id="painel-processando" style="{{ $preparando ? 'display:none' : '' }}">

            {{-- Barra de progresso com percentual real --}}
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

            {{-- Cards de métricas --}}
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
    </div>
</div>

{{-- Amostra de erros --}}
<div id="painel-erros" style="display:none">
    <div class="card card-danger">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Linhas rejeitadas <small class="ml-1">(amostra dos primeiros 100 erros)</small>
            </h3>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover" id="tabela-erros">
                <thead>
                    <tr>
                        <th style="width:70px">#Linha</th>
                        <th>Dados</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const urlProgresso = '{{ route('pedidos.importar.progresso', $importacao) }}';
    const jaFinalizado = {{ $importacao->status->estaEmAndamento() ? 'false' : 'true' }};

    if (jaFinalizado) {
        @if (!$importacao->status->estaEmAndamento() && !empty($importacao->amostra_erros))
            renderizarErros(@json($importacao->amostra_erros));
        @endif
        return;
    }

    const barra           = document.getElementById('barra-progresso');
    const painelPrep      = document.getElementById('painel-preparando');
    const painelProc      = document.getElementById('painel-processando');
    const formPausar      = document.getElementById('form-pausar');
    const formRetomar     = document.getElementById('form-retomar');
    const formCancelar    = document.getElementById('form-cancelar');

    const source = new EventSource(urlProgresso);

    source.onmessage = function (e) {
        const data = JSON.parse(e.data);
        const ok   = data.linhas_processadas - data.linhas_com_erro;
        const prep = data.status === 'pendente' || data.status === 'lendo';

        // Alterna os painéis conforme a fase
        painelPrep.style.display  = prep ? '' : 'none';
        painelProc.style.display  = prep ? 'none' : '';

        document.getElementById('status-rotulo').textContent = data.status_rotulo;
        document.getElementById('concluido-em').textContent  = data.concluido_em ?? '—';

        if (prep) {
            // Fase de preparação: só atualiza duração e total (se já disponível)
            const elDurPrep = document.getElementById('duracao-prep');
            if (elDurPrep) elDurPrep.textContent = data.duracao ?? '—';

            const msgPrep = document.getElementById('msg-preparando');
            if (msgPrep) {
                msgPrep.textContent = data.status === 'pendente'
                    ? 'Aguardando um leitor disponível...'
                    : 'Lendo e distribuindo o arquivo para processamento...';
            }

            if (data.total_linhas > 0) {
                const elTotalPrep = document.getElementById('total-linhas-prep');
                if (elTotalPrep) elTotalPrep.textContent = fmt(data.total_linhas);
            }
        } else {
            // Fase de processamento: atualiza todas as métricas
            document.getElementById('total-linhas').textContent   = fmt(data.total_linhas);
            document.getElementById('linhas-ok').textContent      = fmt(ok);
            document.getElementById('linhas-erro').textContent    = fmt(data.linhas_com_erro);
            document.getElementById('duracao').textContent        = data.duracao ?? '—';
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
            if (data.amostra_erros?.length > 0) renderizarErros(data.amostra_erros);
        }
    };

    source.onerror = function () {
        if (jaFinalizado) source.close();
    };

    function fmt(n) {
        return Number(n).toLocaleString('pt-BR');
    }

    function renderizarErros(erros) {
        const tbody = document.querySelector('#tabela-erros tbody');
        erros.forEach(item => {
            const msgs = Object.entries(item.erros)
                .map(([campo, m]) => `<em>${campo}</em>: ${m.join(', ')}`)
                .join('<br>');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center">${item.linha}</td>
                <td><small><code>${JSON.stringify(item.dados)}</code></small></td>
                <td><small>${msgs}</small></td>
            `;
            tbody.appendChild(tr);
        });
        document.getElementById('painel-erros').style.display = '';
    }
})();
</script>
@endpush
