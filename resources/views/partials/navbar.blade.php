{{-- ===================================================================
     Navbar principal — AdminLTE 3
     Exibida no topo em todas as páginas autenticadas.
     =================================================================== --}}
<nav class="main-header navbar navbar-expand navbar-white navbar-light">

    {{-- Botão de colapso da sidebar (esquerda) --}}
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                <i class="fas fa-bars"></i>
            </a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="{{ route('dashboard') }}" class="nav-link">Início</a>
        </li>
    </ul>

    {{-- Itens à direita --}}
    <ul class="navbar-nav ml-auto">

        {{-- Menu do usuário --}}
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-user-circle mr-1"></i>
                {{ auth()->user()?->name ?? 'Usuário' }}
                <i class="fas fa-caret-down ml-1"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <a href="{{ route('profile.edit') }}" class="dropdown-item">
                    <i class="fas fa-id-card mr-2"></i>Meu Perfil
                </a>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="fas fa-sign-out-alt mr-2"></i>Sair
                    </button>
                </form>
            </div>
        </li>

        {{-- Botão de colapso (direita) --}}
        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>
    </ul>
</nav>
