@extends('layouts.auth')

@section('titulo', 'Verificar E-mail')

@section('conteudo')
<div class="login-box">
    <div class="login-logo">
        <a href="/"><b>{{ config('app.name') }}</b></a>
    </div>

    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">
                Verifique seu e-mail! Enviamos um link de verificação para o seu endereço de e-mail.
            </p>

            @if (session('status') === 'verification-link-sent')
                <div class="alert alert-success">Um novo link de verificação foi enviado.</div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-block">Reenviar e-mail de verificação</button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="btn btn-link btn-block">Sair</button>
            </form>
        </div>
    </div>
</div>
@endsection
