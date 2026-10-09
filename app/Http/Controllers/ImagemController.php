<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImagemController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'foto.required' => 'Escolha uma imagem.',
            'foto.mimes' => 'Use uma imagem JPG, PNG ou WebP.',
            'foto.max' => 'A imagem não pode ultrapassar 5 MB.',
        ]);

        $ficheiro = $request->file('foto');
        $nome = 'imoveis/'.Str::uuid().'.'.$ficheiro->extension();
        Storage::disk('public')->putFileAs('imoveis', $ficheiro, basename($nome));

        return response()->json([
            'url' => asset('storage/'.$nome),
        ], 201);
    }
}
