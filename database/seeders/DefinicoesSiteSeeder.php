<?php

namespace Database\Seeders;

use App\Models\DefinicaoSite;
use Illuminate\Database\Seeder;

class DefinicoesSiteSeeder extends Seeder
{
    public function run(): void
    {
        DefinicaoSite::query()->updateOrCreate(
            ['chave' => 'site'],
            ['valor' => [
                'nome' => 'João Domingues',
                'email' => 'jmdomingues@remax.pt',
                'telefone' => '+351 917 753 038',
                'whatsapp' => 'https://wa.me/351917753038',
                'redes' => [
                    'instagram' => 'https://www.instagram.com/j.domingues_realestate_pt/',
                    'linkedin' => '',
                    'facebook' => 'https://www.facebook.com/joao.domingues.946517',
                    'whatsapp' => 'https://wa.me/351917753038',
                ],
            ]],
        );
    }
}
