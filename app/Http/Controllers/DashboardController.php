<?php

namespace App\Http\Controllers;

use App\Enums\EstadoPedido;
use App\Http\Resources\ImovelResource;
use App\Http\Resources\PedidoResource;
use App\Models\Imovel;
use App\Models\Pedido;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function show(): JsonResponse
    {
        $recentes = Pedido::query()->with('imovel')->orderByDesc('created_at')->limit(5)->get();
        $ultimos = Imovel::query()->with('imagens')->orderByDesc('created_at')->limit(5)->get();

        return response()->json([
            'imoveis' => [
                'total' => Imovel::query()->count(),
                'publicados' => Imovel::query()->where('publicado', true)->count(),
                'destaque' => Imovel::query()->where('destaque', true)->count(),
            ],
            'pedidos' => [
                'total' => Pedido::query()->count(),
                'novos' => Pedido::query()->where('status', EstadoPedido::Novo->value)->count(),
                'recentes' => PedidoResource::collection($recentes)->resolve(),
            ],
            'ultimosImoveis' => ImovelResource::collection($ultimos)->resolve(),
        ]);
    }
}
