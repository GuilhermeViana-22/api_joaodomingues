<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use RuntimeException;

class PrepararPassport extends Command
{
    protected $signature = 'passport:preparar';

    protected $description = 'Garante as chaves e o cliente de acesso pessoal do Passport';

    public function handle(ClientRepository $clientes): int
    {
        $privada = Passport::keyPath('oauth-private.key');
        $publica = Passport::keyPath('oauth-public.key');

        if (! is_file($privada) || ! is_file($publica)) {
            $estado = $this->call('passport:keys', ['--length' => 2048]);
            if ($estado !== self::SUCCESS || ! is_file($privada) || ! is_file($publica)) {
                $this->components->error('Não foi possível criar as chaves do Passport.');

                return self::FAILURE;
            }
        }

        try {
            $clientes->personalAccessClient('users');
        } catch (RuntimeException) {
            $clientes->createPersonalAccessGrantClient('Painel', 'users');
            $this->components->info('Cliente de acesso pessoal do Passport criado.');
        }

        return self::SUCCESS;
    }
}
