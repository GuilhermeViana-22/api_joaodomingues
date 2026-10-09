<?php

namespace Database\Seeders;

use App\Enums\Perfil;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Os três utilizadores iniciais do painel, com senhas fixas de 12 caracteres.
 *
 * Corre em cada arranque do contentor (start.sh): cria quem ainda não existe
 * e repõe a senha fixa de quem já existe, por isso uma senha trocada no
 * painel volta a esta no deploy seguinte. Nunca mexe no perfil nem no estado.
 */
class UtilizadoresSeeder extends Seeder
{
    public function run(): void
    {
        $utilizadores = [
            ['João Domingues', env('SEED_EMAIL_ADMIN', 'jmdomingues@remax.pt'), Perfil::Administrador, 'd6D3tppJLJhz'],
            ['Suporte', env('SEED_EMAIL_SUPORTE', 'suporte@joaodomingues.pt'), Perfil::Administrador, 'PLb9NN6smirO'],
            ['Editor', env('SEED_EMAIL_EDITOR', 'editor@joaodomingues.pt'), Perfil::Editor, '3P4safQnOrqV'],
        ];

        foreach ($utilizadores as [$nome, $email, $perfil, $senha]) {
            $email = strtolower(trim($email));
            $utilizador = User::query()->where('email', $email)->first();

            if ($utilizador === null) {
                User::query()->create([
                    'name' => $nome,
                    'email' => $email,
                    'password' => $senha,
                    'perfil' => $perfil->value,
                    'ativo' => true,
                ]);
                $this->command?->info("Utilizador criado: {$email} ({$perfil->value})");
            } elseif (! Hash::check($senha, $utilizador->password)) {
                $utilizador->forceFill(['password' => $senha])->save();
                $this->command?->info("Senha reposta: {$email}");
            }
        }
    }
}
