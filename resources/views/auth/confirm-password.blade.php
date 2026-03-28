@extends('layouts.auth')

@section('titulo', 'Confirmar Senha')

@section('conteudo')
<div class="login-box">
    <div class="login-logo">
        <a href="/"><b>{{ config('app.name') }}</b></a>
    </div>

    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">
                Esta área requer confirmação de senha. Por favor, confirme sua senha para continuar.
            </p>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $erro)
                            <li>{{ $erro }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control"
                           placeholder="Senha" required autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Confirmar</button>
            </form>
        </div>
    </div>
</div>
@endsection
