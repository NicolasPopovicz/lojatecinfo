<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportacaoPedidosController;
use App\Http\Controllers\PedidosController;
use App\Http\Controllers\TransportadorasController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login')
)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Rotas específicas de pedidos ANTES do resource (evita conflito com {pedido})
    Route::get('pedidos/importar', [ImportacaoPedidosController::class, 'formulario'])
        ->name('pedidos.importar');
    Route::get('pedidos/importar-historico', [ImportacaoPedidosController::class, 'historico'])
        ->name('pedidos.importar.historico');
    Route::get('pedidos/importacoes', [ImportacaoPedidosController::class, 'listarHistorico'])
        ->name('pedidos.importacoes');
    Route::post('pedidos/importar', [ImportacaoPedidosController::class, 'upload'])
        ->name('pedidos.importar.upload');
    Route::get('pedidos/importar/{importacao}', [ImportacaoPedidosController::class, 'acompanhar'])
        ->name('pedidos.importar.acompanhar');
    Route::get('pedidos/importar/{importacao}/progresso', [ImportacaoPedidosController::class, 'progresso'])
        ->name('pedidos.importar.progresso');
    Route::post('pedidos/importar/{importacao}/pausar', [ImportacaoPedidosController::class, 'pausar'])
        ->name('pedidos.importar.pausar');
    Route::post('pedidos/importar/{importacao}/retomar', [ImportacaoPedidosController::class, 'retomar'])
        ->name('pedidos.importar.retomar');
    Route::delete('pedidos/importar/{importacao}', [ImportacaoPedidosController::class, 'cancelar'])
        ->name('pedidos.importar.cancelar');
    Route::get('pedidos-exportar', [PedidosController::class, 'exportarCsv'])
        ->name('pedidos.exportar-csv');

    Route::resource('pedidos', PedidosController::class);

    Route::resource('transportadoras', TransportadorasController::class);
    Route::get('cep/buscar', [TransportadorasController::class, 'buscarCep'])
        ->name('cep.buscar');
});

require __DIR__.'/settings.php';
