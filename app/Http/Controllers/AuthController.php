<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RedefinirSenhaRequest;
use App\Http\Resources\UtilizadorResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $utilizador = User::query()->where('email', strtolower($request->string('email')->toString()))->first();

        if ($utilizador === null || ! Hash::check($request->string('senha')->toString(), $utilizador->password)) {
            return response()->json(['message' => 'E-mail ou senha incorretos.'], 401);
        }

        if (! $utilizador->ativo) {
            return response()->json(['message' => 'Esta conta está desativada. Fale com um administrador.'], 403);
        }

        $utilizador->forceFill(['ultimo_acesso' => now()])->save();
        $token = $utilizador->createToken('painel')->plainTextToken;

        return response()->json([
            'token' => $token,
            'utilizador' => new UtilizadorResource($utilizador),
        ]);
    }

    public function me(Request $request): UtilizadorResource
    {
        return new UtilizadorResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Sessão terminada.']);
    }

    public function recuperar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Indique o e-mail.',
            'email.email' => 'Indique um e-mail válido.',
        ]);

        try {
            Password::sendResetLink(['email' => strtolower($dados['email'])]);
        } catch (Throwable $e) {
            Log::warning('Falha ao enviar recuperação de senha.', ['erro' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Se o e-mail existir, enviámos instruções para redefinir a senha.',
        ]);
    }

    public function redefinir(RedefinirSenhaRequest $request): JsonResponse
    {
        $estado = Password::reset(
            [
                'email' => strtolower($request->string('email')->toString()),
                'password' => $request->string('senha')->toString(),
                'password_confirmation' => $request->string('senha')->toString(),
                'token' => $request->string('token')->toString(),
            ],
            function (User $utilizador, string $senha) {
                $utilizador->forceFill(['password' => $senha])->save();
                $utilizador->tokens()->delete();
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'O link de recuperação é inválido ou expirou.'], 422);
        }

        return response()->json(['message' => 'Senha atualizada. Já pode entrar.']);
    }
}
