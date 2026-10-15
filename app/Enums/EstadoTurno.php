<?php

namespace App\Enums;

use App\Enums\Concerns\MaquinaDeEstados;

/**
 * Máquina de estados de un turno en la sala de espera.
 *
 * Esperando ─(tarotista toma)─▶ Tomado ─(no entra en 2 min)─▶ Ausente
 * Esperando ─(consultante cancela / cierra la pestaña)─▶ Cancelado
 */
enum EstadoTurno: string
{
    use MaquinaDeEstados;

    case Esperando = 'esperando';
    case Tomado = 'tomado';
    case Cancelado = 'cancelado';
    case Ausente = 'ausente';

    public function siguientes(): array
    {
        return match ($this) {
            self::Esperando => [self::Tomado, self::Cancelado],
            self::Tomado => [self::Ausente],
            self::Cancelado, self::Ausente => [],
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Esperando => 'En la fila',
            self::Tomado => 'Atendido',
            self::Cancelado => 'Cancelado',
            self::Ausente => 'Ausente',
        };
    }
}
