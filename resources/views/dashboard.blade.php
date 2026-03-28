@extends('layouts.admin')

@section('titulo', 'Dashboard')

@section('conteudo')

{{-- ===== Cards de resumo ===== --}}
<div class="row">

    <div class="col-lg-4 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ number_format($total_pedidos, 0, ',', '.') }}</h3>
                <p>Total de Pedidos</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            <a href="{{ route('pedidos.index') }}" class="small-box-footer">
                Ver pedidos <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-4 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>R$ {{ number_format($total_vendido, 2, ',', '.') }}</h3>
                <p>Total Vendido</p>
            </div>
            <div class="icon"><i class="fas fa-dollar-sign"></i></div>
            <a href="{{ route('pedidos.exportar-csv') }}" class="small-box-footer"
               data-loading="Gerando CSV..." data-loading-auto>
                Exportar CSV <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    <div class="col-lg-4 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ number_format($total_transportadoras, 0, ',', '.') }}</h3>
                <p>Transportadoras</p>
            </div>
            <div class="icon"><i class="fas fa-truck"></i></div>
            <a href="{{ route('transportadoras.index') }}" class="small-box-footer">
                Ver transportadoras <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

</div>


{{-- ===== Ações rápidas ===== --}}
<div class="row">
    <div class="col-12">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">Ações rápidas</h3>
            </div>
            <div class="card-body">
                <a href="{{ route('pedidos.create') }}" class="btn btn-primary mr-2">
                    <i class="fas fa-plus mr-1"></i>Novo Pedido
                </a>
                <a href="{{ route('pedidos.importar') }}" class="btn btn-info mr-2">
                    <i class="fas fa-upload mr-1"></i>Importar CSV
                </a>
                <a href="{{ route('transportadoras.create') }}" class="btn btn-warning mr-2">
                    <i class="fas fa-plus mr-1"></i>Nova Transportadora
                </a>
                <a href="{{ route('pedidos.exportar-csv') }}" class="btn btn-success"
                   data-loading="Gerando CSV..." data-loading-auto>
                    <i class="fas fa-download mr-1"></i>Exportar Pedidos
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

