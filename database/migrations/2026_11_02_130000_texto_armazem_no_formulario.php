<?php

use App\Models\TextoSite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

/*
| Opção "Armazém" no "Tipo de imóvel" do formulário de contacto do site.
| Só entra se a chave ainda não existir; em en/fr/es a `fontes` fica com a
| impressão do português, para o tradutor não a refazer.
*/
return new class extends Migration
{
    public const TEXTOS = [
        'pt' => ['form.armazem' => 'Armazém'],
        'en' => ['form.armazem' => 'Warehouse'],
        'fr' => ['form.armazem' => 'Entrepôt'],
        'es' => ['form.armazem' => 'Almacén'],
    ];

    public function up(): void
    {
        foreach (self::TEXTOS as $locale => $textos) {
            $linha = TextoSite::query()->find($locale);
            if ($linha === null) {
                continue; // base vazia: o seeder já traz estes textos
            }

            $conteudo = $linha->conteudo ?? [];
            $fontes = $linha->fontes;
            foreach ($textos as $chave => $texto) {
                if (Arr::has($conteudo, $chave)) {
                    continue;
                }
                Arr::set($conteudo, $chave, $texto);
                if ($locale !== 'pt' && is_array($fontes)) {
                    $fontes[$chave] = md5(self::TEXTOS['pt'][$chave]);
                }
            }
            $linha->conteudo = $conteudo;
            $linha->fontes = $fontes;
            $linha->save();
        }
    }

    public function down(): void
    {
        // Textos editáveis no painel: não há o que repor.
    }
};
