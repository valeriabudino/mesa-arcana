<?php

namespace App\Enums;

/**
 * Tipos de tirada. Cada uno sabe cuántas cartas lleva y el nombre de cada posición.
 * Las coordenadas en la mesa 3D viven en resources/js/mesa/layouts.js.
 */
enum TipoTirada: string
{
    case TresCartas = 'tres_cartas';
    case SiNo = 'si_no';
    case CruzCelta = 'cruz_celta';

    public function nombre(): string
    {
        return match ($this) {
            self::TresCartas => 'Tres cartas',
            self::SiNo => 'Sí o no',
            self::CruzCelta => 'Cruz celta',
        };
    }

    public function cantidad(): int
    {
        return count($this->posiciones());
    }

    /**
     * Nombre de cada posición, en orden (índice = posición 0..n-1).
     *
     * @return list<string>
     */
    public function posiciones(): array
    {
        return match ($this) {
            self::TresCartas => ['Pasado', 'Presente', 'Futuro'],
            self::SiNo => ['Respuesta'],
            self::CruzCelta => [
                'Situación actual',
                'Desafío',
                'Raíz',
                'Pasado reciente',
                'Meta',
                'Futuro cercano',
                'Una misma',
                'Entorno',
                'Esperanzas y miedos',
                'Resultado',
            ],
        };
    }

    public function nombrePosicion(int $posicion): string
    {
        return $this->posiciones()[$posicion];
    }
}
