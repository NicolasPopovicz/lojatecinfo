@extends('layouts.admin')

@section('titulo', 'Editar Transportadora #' . $transportadora->id)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('transportadoras.index') }}">Transportadoras</a></li>
    <li class="breadcrumb-item active">Editar #{{ $transportadora->id }}</li>
@endsection

@section('conteudo')
<div class="card card-warning">
    <div class="card-header">
        <h3 class="card-title">{{ $transportadora->nome }}</h3>
    </div>

    <form method="POST" action="{{ route('transportadoras.update', $transportadora) }}">
        @csrf @method('PUT')

        <div class="card-body">

            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="nome">Nome <span class="text-danger">*</span></label>
                        <input type="text" id="nome" name="nome"
                               class="form-control @error('nome') is-invalid @enderror"
                               value="{{ old('nome', $transportadora->nome) }}" maxlength="100" required>
                        @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="cnpj">CNPJ (somente números) <span class="text-danger">*</span></label>
                        <input type="text" id="cnpj" name="cnpj"
                               class="form-control @error('cnpj') is-invalid @enderror"
                               value="{{ old('cnpj', $transportadora->cnpj) }}" maxlength="14" required>
                        @error('cnpj') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <hr>
            <h6 class="text-muted mb-3"><i class="fas fa-map-marker-alt mr-1"></i>Endereço</h6>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="cep">CEP</label>
                        <div class="input-group">
                            <input type="text" id="cep" class="form-control"
                                   placeholder="00000-000" maxlength="9">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btn-buscar-cep">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <small id="msg-cep" class="text-danger" style="display:none">CEP não encontrado.</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="rua">Rua / Logradouro <span class="text-danger">*</span></label>
                        <input type="text" id="rua" name="rua"
                               class="form-control @error('rua') is-invalid @enderror"
                               value="{{ old('rua', $transportadora->rua) }}" maxlength="150" required>
                        @error('rua') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="numero">Número <span class="text-danger">*</span></label>
                        <input type="text" id="numero" name="numero"
                               class="form-control @error('numero') is-invalid @enderror"
                               value="{{ old('numero', $transportadora->numero) }}" maxlength="10" required>
                        @error('numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="complemento">Complemento</label>
                        <input type="text" id="complemento" name="complemento"
                               class="form-control @error('complemento') is-invalid @enderror"
                               value="{{ old('complemento', $transportadora->complemento) }}" maxlength="100">
                        @error('complemento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="bairro">Bairro <span class="text-danger">*</span></label>
                        <input type="text" id="bairro" name="bairro"
                               class="form-control @error('bairro') is-invalid @enderror"
                               value="{{ old('bairro', $transportadora->bairro) }}" maxlength="150" required>
                        @error('bairro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label for="cidade">Cidade <span class="text-danger">*</span></label>
                        <input type="text" id="cidade" name="cidade"
                               class="form-control @error('cidade') is-invalid @enderror"
                               value="{{ old('cidade', $transportadora->cidade) }}" maxlength="80" required>
                        @error('cidade') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="estado">UF <span class="text-danger">*</span></label>
                        <input type="text" id="estado" name="estado"
                               class="form-control @error('estado') is-invalid @enderror"
                               value="{{ old('estado', $transportadora->estado) }}" maxlength="2"
                               style="text-transform:uppercase" required>
                        @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-warning">
                <i class="fas fa-save mr-1"></i>Atualizar
            </button>
            <a href="{{ route('transportadoras.index') }}" class="btn btn-default ml-2">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const urlBuscarCep = '{{ route('cep.buscar') }}';

    function preencherEndereco(dados) {
        document.getElementById('rua').value    = dados.rua    ?? '';
        document.getElementById('bairro').value = dados.bairro ?? '';
        document.getElementById('cidade').value = dados.cidade ?? '';
        document.getElementById('estado').value = (dados.estado ?? '').toUpperCase();
        document.getElementById('numero').focus();
    }

    async function buscarCep() {
        const cep   = document.getElementById('cep').value.replace(/\D/g, '');
        const msgEl = document.getElementById('msg-cep');
        const btn   = document.getElementById('btn-buscar-cep');

        if (cep.length !== 8) return;
        msgEl.style.display = 'none';
        btn.disabled     = true;
        btn.innerHTML    = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const res = await fetch(`${urlBuscarCep}?cep=${cep}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) { msgEl.style.display = ''; return; }

            preencherEndereco(await res.json());
        } catch (e) {
            console.error('Erro ao buscar CEP:', e);
        } finally {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-search"></i>';
        }
    }

    document.getElementById('btn-buscar-cep').addEventListener('click', buscarCep);
    document.getElementById('cep').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); buscarCep(); }
    });
})();
</script>
@endpush
