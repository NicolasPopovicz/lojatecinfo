<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Painel') — {{ config('app.name') }}</title>

    {{-- Font Awesome 5 --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    {{-- Google Fonts: Source Sans Pro --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    {{-- Bootstrap 4 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    {{-- AdminLTE 3 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    {{-- ===== Navbar principal ===== --}}
    @include('partials.navbar')

    {{-- ===== Sidebar ===== --}}
    @include('partials.sidebar')

    {{-- ===== Área de conteúdo ===== --}}
    <div class="content-wrapper">

        {{-- Cabeçalho da página --}}
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">@yield('titulo', 'Painel')</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item">
                                <a href="{{ route('dashboard') }}">Início</a>
                            </li>
                            @hasSection('breadcrumb')
                                @yield('breadcrumb')
                            @endif
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conteúdo --}}
        <div class="content">
            <div class="container-fluid">

                {{-- Alertas de sessão --}}
                @if (session('sucesso'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('sucesso') }}
                        <button type="button" class="close" data-dismiss="alert">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if (session('info'))
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle mr-2"></i>{{ session('info') }}
                        <button type="button" class="close" data-dismiss="alert">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if (session('erro'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>{{ session('erro') }}
                        <button type="button" class="close" data-dismiss="alert">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong><i class="fas fa-exclamation-triangle mr-2"></i>Corrija os erros abaixo:</strong>
                        <ul class="mb-0 mt-1">
                            @foreach ($errors->all() as $erro)
                                <li>{{ $erro }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="close" data-dismiss="alert">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @yield('conteudo')

            </div>
        </div>
    </div>

    {{-- ===== Rodapé ===== --}}
    @include('partials.footer')

</div>

{{-- ===================================================================
     Overlay de carregamento global
     Exibido ao submeter qualquer form ou clicar em link[data-loading].
     Ocultar com: Loading.hide() — útil para botões que NÃO devem bloquear
     (ex.: busca, paginação). Formulários de exclusão mostram confirmação
     antes, então o overlay só aparece se o usuário confirmar.
     =================================================================== --}}
<div id="loading-overlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;
            align-items:center;justify-content:center;flex-direction:column">
    <div class="spinner-border text-light" style="width:3rem;height:3rem" role="status"></div>
    <p id="loading-msg" class="text-white mt-3 mb-0" style="font-size:1.1rem">Aguarde...</p>
</div>

{{-- jQuery --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
{{-- Bootstrap 4 --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
{{-- AdminLTE 3 --}}
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<script>
(function () {
    const overlay  = document.getElementById('loading-overlay');
    const msgEl    = document.getElementById('loading-msg');

    window.Loading = {
        show: function (msg) {
            msgEl.textContent      = msg || 'Aguarde...';
            overlay.style.display  = 'flex';
        },
        hide: function () {
            overlay.style.display  = 'none';
        },
    };

    // Todos os <form> — exceto os marcados com data-no-loading
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.dataset.noLoading !== undefined) return;

        const msg = form.dataset.loadingMsg || 'Salvando...';
        Loading.show(msg);
    });

    // Links marcados com data-loading (ex.: exportar CSV)
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-loading]');
        if (!el) return;

        Loading.show(el.dataset.loading || 'Aguarde...');

        // Downloads disparam o overlay mas não trocam de página —
        // esconde automaticamente após 4 s para não travar a UI.
        if (el.getAttribute('download') !== null || el.dataset.loadingAuto !== undefined) {
            setTimeout(Loading.hide, 4000);
        }
    });

    // Esconde ao usar o botão Voltar do navegador
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) Loading.hide();
    });
})();
</script>

@stack('scripts')
</body>
</html>
