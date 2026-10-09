<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefinicaoController;
use App\Http\Controllers\ImagemController;
use App\Http\Controllers\ImovelController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\TextoController;
use App\Http\Controllers\UtilizadorController;
use Illuminate\Support\Facades\Route;

/*
| Rotas em /api/v1, com os caminhos que o painel (admin/src/services/rest.ts)
| e o site (web/js/pedidos.js, web/js/idiomas.js) já chamam.
| O prefixo v1 fica na base URL do cliente: http://localhost:8000/api/v1
*/

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/recuperar-senha', [AuthController::class, 'recuperar'])->middleware('throttle:login');
    Route::post('auth/redefinir-senha', [AuthController::class, 'redefinir'])->middleware('throttle:login');

    Route::get('imoveis/destaque', [ImovelController::class, 'destaque']);
    Route::get('imoveis', [ImovelController::class, 'index']);
    Route::get('imoveis/{imovel}', [ImovelController::class, 'show']);

    Route::get('textos', [TextoController::class, 'show']);
    Route::get('definicoes', [DefinicaoController::class, 'show']);

    Route::post('pedidos', [PedidoController::class, 'store'])->middleware('throttle:pedidos');
    Route::post('contact', [PedidoController::class, 'store'])->middleware('throttle:pedidos');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::middleware('ativo')->group(function () {
            Route::get('auth/me', [AuthController::class, 'me']);
            Route::get('dashboard', [DashboardController::class, 'show'])->middleware('modulo:dashboard');

            Route::middleware('modulo:imoveis')->group(function () {
                Route::post('imoveis', [ImovelController::class, 'store']);
                Route::put('imoveis/{imovel}', [ImovelController::class, 'update']);
                Route::patch('imoveis/{imovel}', [ImovelController::class, 'publicar']);
                Route::delete('imoveis/{imovel}', [ImovelController::class, 'destroy']);
                Route::post('imagens', [ImagemController::class, 'store']);
            });

            Route::put('textos', [TextoController::class, 'update'])->middleware('modulo:conteudo');

            Route::middleware('modulo:pedidos')->group(function () {
                Route::get('pedidos', [PedidoController::class, 'index']);
                Route::get('pedidos/{pedido}', [PedidoController::class, 'show']);
                Route::patch('pedidos/{pedido}', [PedidoController::class, 'update']);
                Route::delete('pedidos/{pedido}', [PedidoController::class, 'destroy']);
            });

            Route::middleware('modulo:utilizadores')->group(function () {
                Route::get('utilizadores', [UtilizadorController::class, 'index']);
                Route::post('utilizadores', [UtilizadorController::class, 'store']);
                Route::put('utilizadores/{utilizador}', [UtilizadorController::class, 'update']);
                Route::delete('utilizadores/{utilizador}', [UtilizadorController::class, 'destroy']);
            });

            Route::put('definicoes', [DefinicaoController::class, 'update'])->middleware('modulo:configuracoes');
        });
    });
});
