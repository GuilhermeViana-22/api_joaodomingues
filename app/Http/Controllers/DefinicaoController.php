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

        return response()->json($valor);
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
