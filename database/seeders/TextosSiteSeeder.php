<?php

namespace Database\Seeders;

use App\Models\TextoSite;
use Illuminate\Database\Seeder;

class TextosSiteSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['pt', 'en', 'fr', 'es'] as $locale) {
            $caminho = database_path('seeders/dados/'.$locale.'.json');
            $conteudo = json_decode((string) file_get_contents($caminho), true);

            TextoSite::query()->updateOrCreate(
                ['locale' => $locale],
                ['conteudo' => $conteudo],
            );
        }
    }
}
