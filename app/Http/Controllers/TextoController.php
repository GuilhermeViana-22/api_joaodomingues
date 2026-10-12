<?php

namespace App\Http\Controllers;

use App\Http\Requests\TextosRequest;
use App\Services\TextosSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class TextoController extends Controller
{
    public function __construct(private readonly TextosSiteService $textos) {}

    /** Público (o site lê isto): { pt: {...}, en: {...}, fr: {...}, es: {...} }. */
    public function show(): JsonResponse
    {
        return response()->json($this->textos->obter());
    }

    /**
     * Recebe só o português e devolve os 4 idiomas já traduzidos:
     * { textos: { pt, en, fr, es }, pendentes: ['fr'] }.
     * `pendentes` lista os idiomas em que o tradutor falhou; esses ficam com a
     * tradução anterior e um job tenta de novo sozinho.
     */
    public function update(TextosRequest $request): JsonResponse
    {
        try {
            return response()->json($this->textos->guardar($request->validated('pt')));
        } catch (ValidationException|HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Erro inesperado ao gravar os textos do site.', [
                'mensagem' => $e->getMessage(),
                'ficheiro' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json(['message' => 'Não foi possível guardar os textos. Tente outra vez daqui a pouco.'], 500);
        }
    }
}
