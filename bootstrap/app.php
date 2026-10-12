<?php

use App\Http\Middleware\ExigirModulo;
use App\Http\Middleware\ForcarJson;
use App\Http\Middleware\UtilizadorAtivo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Atrás do Traefik (Dokploy) o HTTPS termina no proxy: sem isto o
        // asset() das fotos sai em http:// e o site em https bloqueia-as.
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            ForcarJson::class,
        ]);

        $middleware->alias([
            'modulo' => ExigirModulo::class,
            'ativo' => UtilizadorAtivo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $primeira = collect($e->errors())->flatten()->first();

            return response()->json([
                'message' => $primeira ?: 'Dados inválidos.',
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Sessão expirada.'], 401);
        });

        // O Laravel converte o ModelNotFoundException (route model binding) em
        // NotFoundHttpException antes de chegar aqui, por isso apanha-se este.
        // Mensagens próprias de abort(404, '...') mantêm-se; a do Eloquent
        // ("No query results for model...") e rotas inexistentes viram frase PT.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $doModelo = $e->getPrevious() instanceof ModelNotFoundException;
            $mensagem = ! $doModelo && $e->getMessage() !== '' && ! str_starts_with($e->getMessage(), 'The route ')
                ? $e->getMessage()
                : 'O registo já não existe.';

            return response()->json(['message' => $mensagem], 404);
        });
    })->create();
