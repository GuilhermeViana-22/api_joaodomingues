<?php

namespace App\Console\Commands;

use App\Enums\Perfil;
use App\Models\User;
use Illuminate\Console\Command;

class CriarAdministrador extends Command
{
    protected $signature = 'admin:criar {--nome=} {--email=} {--senha=}';

    protected $description = 'Cria o primeiro administrador do painel';

    public function handle(): int
    {
        $nome = trim((string) ($this->option('nome') ?: $this->ask('Nome')));
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail'))));
        $senha = $this->option('senha') ?: $this->secret('Senha');

        if ($nome === '') {
            $this->error('Indique o nome.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('E-mail inválido.');

            return self::FAILURE;
        }

        if (! is_string($senha) || strlen($senha) < 8 || $this->senhaFraca($senha)) {
            $this->error('A senha precisa de pelo menos 8 caracteres e não pode ser uma senha óbvia.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('Já existe um utilizador com este e-mail.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $nome,
            'email' => $email,
            'password' => $senha,
            'perfil' => Perfil::Administrador->value,
            'ativo' => true,
        ]);

        $this->info('Administrador criado. Entre no painel com este e-mail.');

        return self::SUCCESS;
    }

    private function senhaFraca(string $senha): bool
    {
        return in_array(strtolower($senha), ['admin123', 'password', '12345678', '123456789'], true);
    }
}
