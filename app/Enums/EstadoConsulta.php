<?php

namespace App\Enums;

use App\Enums\Concerns\MaquinaDeEstados;

/** Activa ─(tarotista finaliza)─▶ Finalizada. El chat funciona mientras esté Activa. */
enum EstadoConsulta: string
{
    use MaquinaDeEstados;

    case Activa = 'activa';
    case Finalizada = 'finalizada';

    public function siguientes(): array
    {
        return match ($this) {
            self::Activa => [self::Finalizada],
            self::Finalizada => [],
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activa => 'En curso',
            self::Finalizada => 'Finalizada',
        };
    }
}
