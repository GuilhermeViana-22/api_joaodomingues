<?php

namespace App\Http\Controllers;

use App\Http\Requests\DefinicoesRequest;
use App\Models\DefinicaoSite;
use Illuminate\Http\JsonResponse;

class DefinicaoController extends Controller
{
    public function show(): JsonResponse
    {
        $valor = DefinicaoSite::query()->find('site')?->valor ?? [
            'nome' => 'João Domingues',
            'email' => 'jmdomingues@remax.pt',
            'telefone' => '',
            'whatsapp' => '',
            'redes' => [
                'instagram' => '',
                'linkedin' => '',
                'facebook' => '',
                'whatsapp' => '',
            ],
        ];

        // Foto da secção "Sobre mim" (null: o site usa a sua)
        return response()->json([...$valor, 'fotoSobre' => FotoSobreController::url()]);
    }

    public function update(DefinicoesRequest $request): JsonResponse
    {
        DefinicaoSite::query()->updateOrCreate(
            ['chave' => 'site'],
            ['valor' => $request->validated()],
        );

        return $this->show();
    }
}
