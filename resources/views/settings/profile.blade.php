@extends('layouts.admin')

@section('titulo', 'Meu Perfil')

@section('breadcrumb')
    <li class="breadcrumb-item active">Meu Perfil</li>
@endsection

@section('conteudo')
<div class="row">
    <div class="col-md-6">

        {{-- Dados do perfil --}}
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Dados Pessoais</h3>
            </div>
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf @method('PATCH')

                <div class="card-body">
                    @if (session('status') === 'profile-updated')
                        <div class="alert alert-success">Perfil atualizado com sucesso.</div>
                    @endif

                    <div class="form-group">
                        <label for="name">Nome</label>
                        <input type="text" id="name" name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', auth()->user()->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', auth()->user()->email) }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        @if ($mustVerifyEmail && !auth()->user()->hasVerifiedEmail())
                            <div class="alert alert-warning mt-2">
                                E-mail não verificado.
                                <form method="POST" action="{{ route('verification.send') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-link p-0">Reenviar verificação</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i>Salvar
                    </button>
                </div>
            </form>
        </div>

        {{-- Alterar senha --}}
        <div class="card card-warning">
            <div class="card-header">
                <h3 class="card-title">Alterar Senha</h3>
            </div>
            <form method="POST" action="{{ route('user-password.update') }}">
                @csrf @method('PUT')

                <div class="card-body">
                    @if (session('status') === 'password-updated')
                        <div class="alert alert-success">Senha atualizada com sucesso.</div>
                    @endif

                    <div class="form-group">
                        <label for="current_password">Senha atual</label>
                        <input type="password" id="current_password" name="current_password"
                               class="form-control @error('current_password', 'updatePassword') is-invalid @enderror">
                        @error('current_password', 'updatePassword')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">Nova senha</label>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password', 'updatePassword') is-invalid @enderror">
                        @error('password', 'updatePassword')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirmar nova senha</label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="form-control">
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key mr-1"></i>Alterar Senha
                    </button>
                </div>
            </form>
        </div>

        {{-- Excluir conta --}}
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Excluir Conta</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Após a exclusão, todos os dados da conta serão permanentemente removidos.
                </p>
                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#modal-excluir">
                    <i class="fas fa-trash mr-1"></i>Excluir minha conta
                </button>
            </div>
        </div>

    </div>
</div>

{{-- Modal confirmação de exclusão --}}
<div class="modal fade" id="modal-excluir" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white">Confirmar exclusão</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('profile.destroy') }}">
                @csrf @method('DELETE')
                <div class="modal-body">
                    <p>Esta ação é irreversível. Confirme sua senha para continuar.</p>
                    <div class="form-group">
                        <label for="del_password">Senha</label>
                        <input type="password" id="del_password" name="password"
                               class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                               required>
                        @error('password', 'userDeletion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir permanentemente</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
