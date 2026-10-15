<?php

namespace App\Enums;

/** Qué responde cada carta en una tirada de sí o no. */
enum SiNo: string
{
    case Si = 'si';
    case No = 'no';
    case Quizas = 'quizas';

    /**
     * La regla de la tirada de sí o no: al derecho vale lo que dice la carta;
     * invertida, la respuesta se debilita un paso (Sí → Quizás → No).
     */
    public function segun(bool $invertida): self
    {
        if (! $invertida) {
            return $this;
        }

        return match ($this) {
            self::Si => self::Quizas,
            self::Quizas, self::No => self::No,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Si => 'Sí',
            self::No => 'No',
            self::Quizas => 'Quizás',
        };
    }
}
