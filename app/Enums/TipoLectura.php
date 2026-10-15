<?php

namespace App\Enums;

/**
 * Tipos de lectura que se pueden hacer en una consulta (también son los "servicios"
 * que la consultante pide desde el chat). Las tiradas saben cuántas cartas llevan y
 * el nombre de cada posición; las coordenadas en la mesa 3D viven en resources/js/mesa/layouts.js.
 */
enum TipoLectura: string
{
    case TresCartas = 'tres_cartas';
    case SiNo = 'si_no';
    case CruzCelta = 'cruz_celta';
    case CartaAstral = 'carta_astral';

    public function nombre(): string
    {
        return match ($this) {
            self::TresCartas => 'Tirada de tres cartas',
            self::SiNo => 'Sí o no',
            self::CruzCelta => 'Cruz celta',
            self::CartaAstral => 'Carta astral',
        };
    }

    /** ¿Se hace con el mazo (mezclar, elegir, revelar)? */
    public function esDeCartas(): bool
    {
        return $this !== self::CartaAstral;
    }

    /** ¿Se pueden pedir cartas aclaratorias? (En el sí o no la respuesta es una sola.) */
    public function admiteAclaratorias(): bool
    {
        return $this === self::TresCartas || $this === self::CruzCelta;
    }

    /** Dónde arranca la máquina de estados de una lectura de este tipo. */
    public function estadoInicial(): EstadoLectura
    {
        return $this->esDeCartas() ? EstadoLectura::Lista : EstadoLectura::EsperandoDatos;
    }

    public function cantidad(): int
    {
        return count($this->posiciones());
    }

    /**
     * Nombre de cada posición de la tirada, en orden (índice = posición 0..n-1).
     * La carta astral no tiene cartas: lista vacía.
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
            self::CartaAstral => [],
        };
    }

    public function nombrePosicion(int $posicion): string
    {
        return $this->posiciones()[$posicion];
    }

    /** @return list<self> */
    public static function tiradas(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => $t->esDeCartas()));
    }
}
