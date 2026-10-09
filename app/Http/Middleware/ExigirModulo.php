<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigirModulo
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $utilizador = $request->user();

        if ($utilizador === null || ! $utilizador->ativo) {
            abort(403, 'Esta conta está desativada. Fale com um administrador.');
        }

        if (! $utilizador->pode($modulo)) {
            abort(403, 'Não tem permissão para esta ação.');
        }

        return $next($request);
    }
}
