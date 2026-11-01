<?php

use App\Models\TextoSite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

/*
| Textos das áreas novas na linha de características dos imóveis (área útil,
| bruta e de terreno). Só entram as chaves que ainda não existem na base, para
| não apagar o que já tenha sido editado no painel; em en/fr/es a `fontes`
| fica com a impressão do português, para o tradutor não as refazer.
*/
return new class extends Migration
{
    public const TEXTOS = [
        'pt' => [
            'card.areaUtil' => '{{n}} m² úteis',
            'card.areaBruta' => '{{n}} m² brutos',
            'card.areaTerreno' => '{{n}} m² de terreno',
        ],
        'en' => [
            'card.areaUtil' => '{{n}} m² net',
            'card.areaBruta' => '{{n}} m² gross',
            'card.areaTerreno' => '{{n}} m² plot',
        ],
        'fr' => [
            'card.areaUtil' => '{{n}} m² utiles',
            'card.areaBruta' => '{{n}} m² bruts',
            'card.areaTerreno' => '{{n}} m² de terrain',
        ],
        'es' => [
            'card.areaUtil' => '{{n}} m² útiles',
            'card.areaBruta' => '{{n}} m² brutos',
            'card.areaTerreno' => '{{n}} m² de parcela',
        ],
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
