<?php

namespace App\Enums;

/** Los doce signos del zodíaco tropical, en orden desde 0° Aries (cada uno ocupa 30°). */
enum Signo: string
{
    case Aries = 'aries';
    case Tauro = 'tauro';
    case Geminis = 'geminis';
    case Cancer = 'cancer';
    case Leo = 'leo';
    case Virgo = 'virgo';
    case Libra = 'libra';
    case Escorpio = 'escorpio';
    case Sagitario = 'sagitario';
    case Capricornio = 'capricornio';
    case Acuario = 'acuario';
    case Piscis = 'piscis';

    /** El signo en el que cae una longitud eclíptica (0..360). */
    public static function deLongitud(float $longitud): self
    {
        return self::cases()[intdiv((int) floor(fmod(fmod($longitud, 360) + 360, 360)), 30)];
    }

    /** "23°30'": grado y minutos dentro del signo. */
    public static function gradoEnSigno(float $longitud): string
    {
        $dentro = fmod(fmod(fmod($longitud, 360) + 360, 360), 30); // 0..30 dentro del signo
        $grados = (int) floor($dentro);
        $minutos = (int) floor(($dentro - $grados) * 60);

        return sprintf("%d°%02d'", $grados, $minutos);
    }

    public function nombre(): string
    {
        return match ($this) {
            self::Geminis => 'Géminis',
            self::Cancer => 'Cáncer',
            default => ucfirst($this->value),
        };
    }

    public function simbolo(): string
    {
        return ['♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓'][array_search($this, self::cases(), true)];
    }
}
