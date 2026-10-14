<?php

namespace App\Enums;

enum Rol: string
{
    case Tarotista = 'tarotista';
    case Consultante = 'consultante';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Tarotista => 'Tarotista',
            self::Consultante => 'Consultante',
        };
    }
}
