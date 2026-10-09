<?php

namespace App\Models;

use App\Enums\Perfil;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'perfil',
        'ativo',
        'permissoes',
        'ultimo_acesso',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
            'permissoes' => 'array',
            'ultimo_acesso' => 'datetime',
        ];
    }

    public function pode(string $modulo): bool
    {
        $proprias = $this->permissoes;
        if (is_array($proprias) && $proprias !== []) {
            return in_array($modulo, $proprias, true);
        }

        $perfil = Perfil::tryFrom((string) $this->perfil);

        return $perfil !== null && in_array($modulo, $perfil->modulos(), true);
    }
}
