<?php

namespace App\Http\Controllers;

use App\Models\DefinicaoSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Foto da secção "Sobre mim" do site (#especialista).
 *
 * O painel recorta a foto no browser no formato exato do site (moldura de
 * 440×540, gravada a 880×1080 para ecrãs retina), por isso aqui só se aceita
 * esse tamanho: uma foto noutro formato ficava cortada de outra maneira.
 * Sem foto guardada, o site usa a sua (web/imagens/joao_foto_perfil.jpg).
 */
class FotoSobreController extends Controller
{
    public const LARGURA = 880;

    public const ALTURA = 1080;

    private const CHAVE = 'foto_sobre';

    public static function url(): ?string
    {
        return DefinicaoSite::query()->find(self::CHAVE)?->valor['url'] ?? null;
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:width='.self::LARGURA.',height='.self::ALTURA],
        ], [
            'foto.required' => 'Escolha uma imagem.',
            'foto.mimes' => 'Use uma imagem JPG, PNG ou WebP.',
            'foto.max' => 'A imagem não pode ultrapassar 5 MB.',
            'foto.dimensions' => 'A foto tem de ter '.self::LARGURA.'×'.self::ALTURA.' px. Recorte-a no painel antes de enviar.',
        ]);

        $ficheiro = $request->file('foto');
        $caminho = 'site/sobre-'.Str::uuid().'.'.$ficheiro->extension();
        Storage::disk('public')->putFileAs('site', $ficheiro, basename($caminho));

        $anterior = DefinicaoSite::query()->find(self::CHAVE)?->valor['caminho'] ?? null;

        DefinicaoSite::query()->updateOrCreate(
            ['chave' => self::CHAVE],
            ['valor' => ['url' => asset('storage/'.$caminho), 'caminho' => $caminho]],
        );

        // Só depois de gravar: se a base falhasse, a foto antiga continuava a servir
        if ($anterior) {
            Storage::disk('public')->delete($anterior);
        }

        return response()->json(['fotoSobre' => self::url()], 201);
    }

    /** Volta à foto original do site. */
    public function destroy(): JsonResponse
    {
        $definicao = DefinicaoSite::query()->find(self::CHAVE);
        if ($definicao) {
            Storage::disk('public')->delete($definicao->valor['caminho'] ?? '');
            $definicao->delete();
        }

        return response()->json(['fotoSobre' => null]);
    }
}
