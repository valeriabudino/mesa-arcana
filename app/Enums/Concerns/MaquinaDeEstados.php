<?php

namespace App\Enums\Concerns;

/**
 * Lo común a las máquinas de estado (Turno, Consulta, Lectura): cada enum define
 * su tabla de transiciones en siguientes() y su texto en etiqueta(); acá se valida.
 */
trait MaquinaDeEstados
{
    /** @return list<self> */
    abstract public function siguientes(): array;

    abstract public function etiqueta(): string;

    public function puedePasarA(self $nuevo): bool
    {
        return in_array($nuevo, $this->siguientes(), true);
    }

    public function esFinal(): bool
    {
        return $this->siguientes() === [];
    }
}
