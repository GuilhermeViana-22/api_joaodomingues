<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarPedidoRequest;
use App\Http\Requests\PedidoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\Pedido;
use App\Services\PedidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PedidoController extends Controller
{
    public function __construct(private readonly PedidoService $pedidos) {}

    public function store(PedidoRequest $request): JsonResponse
    {
        $pedido = $this->pedidos->receber($request->validated());

        return response()->json([
            'message' => 'Pedido recebido.',
            'id' => $pedido->id,
        ], 201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $consulta = Pedido::query()->with('imovel');

        if ($q = trim($request->string('q')->toString())) {
            $consulta->where(function ($w) use ($q) {
                $w->where('nome', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $consulta->where('status', $request->string('status')->toString());
        }
        if ($request->filled('imovel_id')) {
            $consulta->where('imovel_id', $request->string('imovel_id')->toString());
        }
        if ($request->filled('desde')) {
            $consulta->whereDate('created_at', '>=', $request->date('desde'));
        }
        if ($request->filled('ate')) {
            $consulta->whereDate('created_at', '<=', $request->date('ate'));
        }

        $consulta->orderByDesc('created_at');

        if ($request->boolean('paginar')) {
            return PedidoResource::collection(
                $consulta->paginate(min($request->integer('per_page', 15), 100))->withQueryString()
            );
        }

        return PedidoResource::collection($consulta->get());
    }

    public function show(Pedido $pedido): PedidoResource
    {
        return new PedidoResource($pedido->load('imovel'));
    }

    public function update(ActualizarPedidoRequest $request, Pedido $pedido): PedidoResource
    {
        return new PedidoResource($this->pedidos->actualizar(
            $pedido,
            $request->user(),
            $request->string('status')->toString(),
            $request->exists('observacoes') ? $request->string('observacoes')->toString() : null,
        ));
    }

    public function destroy(Pedido $pedido): Response
    {
        $pedido->delete();

        return response()->noContent();
    }
}
