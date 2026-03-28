<?php

use App\Http\Controllers\Api\PedidosApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Pedidos
|--------------------------------------------------------------------------
| Autenticação via Sanctum (Bearer token).
| Endpoints públicos para fins de demonstração — proteja em produção.
|--------------------------------------------------------------------------
*/

Route::prefix('pedidos')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PedidosApiController::class, 'index']);
    Route::get('{id}', [PedidosApiController::class, 'show']);
    Route::post('/', [PedidosApiController::class, 'store']);
});
