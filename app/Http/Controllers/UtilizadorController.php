<?php

namespace App\Http\Controllers;

use App\Http\Requests\UtilizadorRequest;
use App\Http\Resources\UtilizadorResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class UtilizadorController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UtilizadorResource::collection(User::query()->orderByDesc('created_at')->get());
    }

    public function store(UtilizadorRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $utilizador = User::query()->create([
            'name' => $dados['nome'],
            'email' => strtolower($dados['email']),
            'password' => $dados['senha'],
            'perfil' => $dados['perfil'],
            'ativo' => $dados['ativo'],
        ]);

        return (new UtilizadorResource($utilizador))->response()->setStatusCode(201);
    }

    public function update(UtilizadorRequest $request, User $utilizador): UtilizadorResource
    {
        $dados = $request->validated();
        $utilizador->fill([
            'name' => $dados['nome'],
            'email' => strtolower($dados['email']),
            'perfil' => $dados['perfil'],
            'ativo' => $dados['ativo'],
        ]);

        if (! empty($dados['senha'])) {
            $utilizador->password = $dados['senha'];
            $utilizador->tokens()->delete();
        }

        $utilizador->save();

        return new UtilizadorResource($utilizador);
    }

    public function destroy(Request $request, User $utilizador): JsonResponse|Response
    {
        if ($request->user()?->is($utilizador)) {
            return response()->json(['message' => 'Não pode eliminar a sua própria conta.'], 422);
        }

        $utilizador->tokens()->delete();
        $utilizador->delete();

        return response()->noContent();
    }
}
