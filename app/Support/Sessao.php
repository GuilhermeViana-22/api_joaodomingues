<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Sessao
{
    /**
     * Visitante sem token: null.
     * Token inválido: 401. Conta inactiva: 403.
     * `Sanctum::actingAs` autentica sem cabeçalho Bearer; por isso o guarda
     * é consultado mesmo quando o pedido não traz token.
     */
    public static function opcional(Request $request): ?User
    {
        $utilizador = Auth::guard('sanctum')->user();

        if (! $utilizador instanceof User) {
            if (! $request->bearerToken()) {
                return null;
            }

            abort(401, 'Sessão expirada.');
        }

        if (! $utilizador->ativo) {
            abort(403, 'Esta conta está desativada. Fale com um administrador.');
        }

        return $utilizador;
    }
}
