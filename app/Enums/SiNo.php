<?php

namespace App\Enums;

/** Qué responde cada carta en una tirada de sí o no. */
enum SiNo: string
{
    case Si = 'si';
    case No = 'no';
    case Quizas = 'quizas';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Si => 'Sí',
            self::No => 'No',
            self::Quizas => 'Quizás',
        };
    }
}
