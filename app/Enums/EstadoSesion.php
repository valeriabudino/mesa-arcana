<?php

namespace App\Enums;

/**
 * Máquina de estados de una sesión de lectura.
 *
 * Esperando ─(entra la consultante)─▶ Lista ─(mezclar)─▶ Eligiendo
 * Eligiendo ─(eligió n cartas)─▶ Revelando ─(reveló todas)─▶ Finalizada
 */
enum EstadoSesion: string
{
    case Esperando = 'esperando';
    case Lista = 'lista';
    case Eligiendo = 'eligiendo';
    case Revelando = 'revelando';
    case Finalizada = 'finalizada';

    /**
     * Estados a los que se puede pasar desde el actual.
     *
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Esperando => [self::Lista],
            self::Lista => [self::Eligiendo],
            self::Eligiendo => [self::Revelando],
            self::Revelando => [self::Finalizada],
            self::Finalizada => [],
        };
    }

    public function puedePasarA(self $nuevo): bool
    {
        return in_array($nuevo, $this->siguientes(), true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Esperando => 'Esperando a la consultante',
            self::Lista => 'Lista para mezclar',
            self::Eligiendo => 'Eligiendo cartas',
            self::Revelando => 'Revelando',
            self::Finalizada => 'Finalizada',
        };
    }
}
