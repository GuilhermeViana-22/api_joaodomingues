<?php

namespace App\Enums;

enum Perfil: string
{
    case Administrador = 'administrador';
    case Editor = 'editor';

    /** Módulos do painel. Igual a admin/src/lib/permissoes.ts. */
    public function modulos(): array
    {
        return match ($this) {
            self::Administrador => ['dashboard', 'imoveis', 'conteudo', 'pedidos', 'utilizadores', 'configuracoes'],
            self::Editor => ['dashboard', 'imoveis', 'conteudo', 'pedidos'],
        };
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
