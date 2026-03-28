{{-- ===================================================================
     Sidebar — AdminLTE 3
     Navegação lateral presente em todas as páginas autenticadas.
     Usa request()->routeIs() para marcar o item ativo.
     =================================================================== --}}
<aside class="main-sidebar sidebar-dark-primary elevation-4">

    {{-- Logo / nome do app --}}
    <a href="{{ route('dashboard') }}" class="brand-link">
        <i class="fas fa-store brand-image img-circle elevation-3"
           style="font-size:1.4rem;line-height:2rem;text-align:center;opacity:.8"></i>
        <span class="brand-text font-weight-light">{{ config('app.name') }}</span>
    </a>

    <div class="sidebar">

        {{-- Usuário logado --}}
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <i class="fas fa-user-circle fa-2x text-white" style="line-height:1"></i>
            </div>
            <div class="info">
                <a href="{{ route('profile.edit') }}" class="d-block">
                    {{ auth()->user()?->name ?? 'Usuário' }}
                </a>
            </div>
        </div>

        {{-- Menu de navegação --}}
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview"
                role="menu" data-accordion="false">

                {{-- Dashboard --}}
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                {{-- Pedidos --}}
                <li class="nav-item {{ request()->routeIs('pedidos.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('pedidos.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>
                            Pedidos
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('pedidos.index') }}"
                               class="nav-link {{ request()->routeIs('pedidos.index') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Listar Pedidos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('pedidos.create') }}"
                               class="nav-link {{ request()->routeIs('pedidos.create') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Novo Pedido</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('pedidos.importar') }}"
                               class="nav-link {{ request()->routeIs('pedidos.importar') || request()->routeIs('pedidos.importar.upload') || request()->routeIs('pedidos.importar.acompanhar') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Importar CSV</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('pedidos.importacoes') }}"
                               class="nav-link {{ request()->routeIs('pedidos.importacoes') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Histórico de Importações</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- Transportadoras --}}
                <li class="nav-item {{ request()->routeIs('transportadoras.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link {{ request()->routeIs('transportadoras.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-truck"></i>
                        <p>
                            Transportadoras
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('transportadoras.index') }}"
                               class="nav-link {{ request()->routeIs('transportadoras.index') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Listar</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('transportadoras.create') }}"
                               class="nav-link {{ request()->routeIs('transportadoras.create') ? 'active' : '' }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Nova Transportadora</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-header">CONTA</li>

                {{-- Perfil --}}
                <li class="nav-item">
                    <a href="{{ route('profile.edit') }}"
                       class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-user-cog"></i>
                        <p>Meu Perfil</p>
                    </a>
                </li>

                {{-- Logout --}}
                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-link btn btn-link text-left w-100"
                                style="background:none;border:none">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>Sair</p>
                        </button>
                    </form>
                </li>

            </ul>
        </nav>
    </div>
</aside>
