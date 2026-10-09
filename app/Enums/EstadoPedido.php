<?php

namespace App\Enums;

enum EstadoPedido: string
{
    case Novo = 'novo';
    case Visualizado = 'visualizado';
    case EmContacto = 'em-contacto';
    case Finalizado = 'finalizado';

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Novo => 'Novo',
            self::Visualizado => 'Visualizado',
            self::EmContacto => 'Em contacto',
            self::Finalizado => 'Finalizado',
        };
    }
}
