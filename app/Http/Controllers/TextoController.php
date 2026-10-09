<?php

namespace App\Http\Controllers;

use App\Http\Requests\TextosRequest;
use App\Models\TextoSite;
use Illuminate\Http\JsonResponse;

class TextoController extends Controller
{
    public function show(): JsonResponse
    {
        $porLocale = TextoSite::query()->get()->keyBy('locale');
        $textos = [];

        foreach (['pt', 'en', 'fr', 'es'] as $locale) {
            $textos[$locale] = $porLocale->get($locale)?->conteudo ?? (object) [];
        }

        return response()->json($textos);
    }

    public function update(TextosRequest $request): JsonResponse
    {
        foreach (['pt', 'en', 'fr', 'es'] as $locale) {
            TextoSite::query()->updateOrCreate(
                ['locale' => $locale],
                ['conteudo' => $request->input($locale)],
            );
        }

        return $this->show();
    }
}
