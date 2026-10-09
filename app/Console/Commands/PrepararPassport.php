<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
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
            File::ensureDirectoryExists(dirname($privada));
            $estado = $this->call('passport:keys', ['--length' => 2048]);
            if ($estado !== self::SUCCESS || ! is_file($privada) || ! is_file($publica)) {
                $this->components->error('Não foi possível criar as chaves do Passport.');

                return self::FAILURE;
            }
        }

        // O start.sh dá 775 a storage/; o Passport recusa chaves assim.
        chmod($privada, 0600);
        chmod($publica, 0600);

        try {
            $clientes->personalAccessClient('users');
        } catch (RuntimeException) {
            $clientes->createPersonalAccessGrantClient('Painel', 'users');
            $this->components->info('Cliente de acesso pessoal do Passport criado.');
        }

        return self::SUCCESS;
    }
}
