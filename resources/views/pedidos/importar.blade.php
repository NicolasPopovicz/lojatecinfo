@extends('layouts.admin')

@section('titulo', 'Importar Pedidos')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('pedidos.index') }}">Pedidos</a></li>
    <li class="breadcrumb-item active">Importar CSV</li>
@endsection

@section('conteudo')
<div class="row">

    {{-- Formulário de upload --}}
    <div class="col-md-5">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Upload do arquivo</h3>
            </div>
            <form method="POST" action="{{ route('pedidos.importar.upload') }}" enctype="multipart/form-data"
                  data-loading-msg="Enviando arquivo...">
                @csrf
                <div class="card-body">
                    <div class="form-group">
                        <label for="arquivo">Arquivo CSV <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input @error('arquivo') is-invalid @enderror"
                                   id="arquivo" name="arquivo" accept=".csv" required>
                            <label class="custom-file-label" for="arquivo">Escolher arquivo...</label>
                        </div>
                        @error('arquivo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload mr-1"></i>Iniciar Importação
                    </button>
                    <a href="{{ route('pedidos.index') }}" class="btn btn-default ml-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Formato esperado --}}
    <div class="col-md-7">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i>Formato esperado</h3>
            </div>
            <div class="card-body">
                <p>Separador: <strong>ponto e vírgula (;)</strong> — Codificação: <strong>UTF-8</strong></p>
                <pre class="bg-light p-2 rounded"><code>descricao;nomecliente;produto;preco;quantidade
Pedido via site;João Silva;iPhone 15;4999.90;2
Compra presencial;Maria Costa;MacBook Pro M3;14999,00;1</code></pre>
                <ul class="mb-0">
                    <li><strong>Total</strong> é calculado automaticamente (preço × quantidade).</li>
                    <li>Preço aceita ponto (<code>9999.90</code>) ou vírgula (<code>9999,90</code>) como decimal.</li>
                    <li>Linhas com erro são ignoradas — as válidas são importadas normalmente.</li>
                    <li>Tamanho máximo: <strong>256 MB</strong>.</li>
                </ul>
            </div>
        </div>
    </div>

</div>

{{-- Histórico de importações --}}
<div id="card-historico" style="{{ $historico->isEmpty() ? 'display:none' : '' }}">
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Importações recentes</h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Arquivo</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Importadas</th>
                    <th class="text-right">Erros</th>
                    <th>Status</th>
                    <th>Duração</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-historico">
                @foreach ($historico as $item)
                @php
                    $badgeClass = match ($item->status->value) {
                        'concluido'   => 'badge-success',
                        'processando' => 'badge-info',
                        'pausada'     => 'badge-warning',
                        'pendente'    => 'badge-secondary',
                        default       => 'badge-danger',
                    };
                @endphp
                <tr>
                    <td>{{ $item->id }}</td>
                    <td>{{ $item->arquivo_original }}</td>
                    <td class="text-right">{{ number_format($item->total_linhas, 0, ',', '.') }}</td>
                    <td class="text-right text-success">
                        {{ number_format(max(0, $item->linhas_processadas - $item->linhas_com_erro), 0, ',', '.') }}
                    </td>
                    <td class="text-right {{ $item->linhas_com_erro > 0 ? 'text-danger' : '' }}">
                        {{ number_format($item->linhas_com_erro, 0, ',', '.') }}
                    </td>
                    <td><span class="badge {{ $badgeClass }}">{{ $item->status->rotulo() }}</span></td>
                    <td>{{ $item->duracao ?? '—' }}</td>
                    <td>
                        @if ($item->status->estaEmAndamento())
                            <a href="{{ route('pedidos.importar.acompanhar', $item) }}"
                               class="btn btn-xs btn-info">
                                <i class="fas fa-eye mr-1"></i>Acompanhar
                            </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('arquivo').addEventListener('change', function () {
    this.nextElementSibling.textContent = this.files[0]?.name ?? 'Escolher arquivo...';
});

(function () {
    const urlHistorico  = '{{ route('pedidos.importar.historico') }}';
    const cardHistorico = document.getElementById('card-historico');
    const tbody         = document.getElementById('tbody-historico');

    const badgeClass = {
        concluido:   'badge-success',
        processando: 'badge-info',
        pausada:     'badge-warning',
        pendente:    'badge-secondary',
    };

    let intervalo = null;

    function renderTabela(itens) {
        tbody.innerHTML = itens.map(i => `
            <tr>
                <td>${i.id}</td>
                <td>${i.arquivo_original}</td>
                <td class="text-right">${i.total_linhas_fmt}</td>
                <td class="text-right text-success">${i.importadas_fmt}</td>
                <td class="text-right ${i.erros > 0 ? 'text-danger' : ''}">${i.erros_fmt}</td>
                <td><span class="badge ${badgeClass[i.status] ?? 'badge-danger'}">${i.status_rotulo}</span></td>
                <td>${i.duracao}</td>
                <td>${i.em_andamento
                    ? `<a href="${i.url_acompanhar}" class="btn btn-xs btn-info"><i class="fas fa-eye mr-1"></i>Acompanhar</a>`
                    : ''
                }</td>
            </tr>
        `).join('');
    }

    async function atualizar() {
        try {
            const res  = await fetch(urlHistorico, { headers: { Accept: 'application/json' } });
            const data = await res.json();

            if (data.itens.length > 0) {
                cardHistorico.style.display = '';
                renderTabela(data.itens);
            }

            // Para o polling quando não houver mais nada em andamento
            if (!data.tem_andamento && intervalo) {
                clearInterval(intervalo);
                intervalo = null;
            }
        } catch (e) {
            console.error('Erro ao atualizar histórico:', e);
        }
    }

    // Inicia polling apenas se houver importações em andamento na carga inicial
    const temAndamento = {{ $historico->contains(fn($i) => $i->status->estaEmAndamento()) ? 'true' : 'false' }};
    if (temAndamento) {
        intervalo = setInterval(atualizar, 2000);
    }

    // Quando a página volta ao foco, retoma o polling se ainda houver andamento
    document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState !== 'visible' || intervalo) return;
        await atualizar();
        const res  = await fetch(urlHistorico, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (data.tem_andamento) {
            intervalo = setInterval(atualizar, 2000);
        }
    });
})();
</script>
@endpush
