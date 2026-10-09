<?php

namespace Database\Seeders;

use App\Enums\Perfil;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Os três utilizadores iniciais do painel.
 *
 * Corre em cada arranque do contentor (start.sh), por isso só cria quem
 * ainda não existe: nunca repõe a senha nem o perfil de uma conta já criada.
 * A senha vem de SEED_SENHA_<CHAVE> no ambiente; sem ela é gerada uma
 * aleatória e mostrada uma única vez no log do deploy.
 */
class UtilizadoresSeeder extends Seeder
{
    public function run(): void
    {
        $utilizadores = [
            'ADMIN' => ['João Domingues', env('SEED_EMAIL_ADMIN', 'jmdomingues@remax.pt'), Perfil::Administrador],
            'SUPORTE' => ['Suporte', env('SEED_EMAIL_SUPORTE', 'suporte@joaodomingues.pt'), Perfil::Administrador],
            'EDITOR' => ['Editor', env('SEED_EMAIL_EDITOR', 'editor@joaodomingues.pt'), Perfil::Editor],
        ];

        foreach ($utilizadores as $chave => [$nome, $email, $perfil]) {
            $email = strtolower(trim($email));
            if (User::query()->where('email', $email)->exists()) {
                continue;
            }

            $senha = env('SEED_SENHA_'.$chave) ?: Str::password(16, symbols: false);

            User::query()->create([
                'name' => $nome,
                'email' => $email,
                'password' => $senha,
                'perfil' => $perfil->value,
                'ativo' => true,
            ]);

            $origem = env('SEED_SENHA_'.$chave) ? 'senha de SEED_SENHA_'.$chave : 'senha: '.$senha;
            $this->command?->info("Utilizador criado: {$email} ({$perfil->value}, {$origem})");
        }
    }
}
