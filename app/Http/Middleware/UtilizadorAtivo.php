<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UtilizadorAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->ativo) {
            abort(403, 'Esta conta está desativada. Fale com um administrador.');
        }

        return $next($request);
    }
}
