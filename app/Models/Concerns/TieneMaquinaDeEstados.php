<?php

namespace App\Models\Concerns;

use App\Exceptions\AccionFueraDeOrden;
use BackedEnum;

/**
 * Para modelos con columna "estado" casteada a un enum con MaquinaDeEstados
 * (Turno, Consulta, Lectura). Es el único lugar donde cambia el estado.
 * No guarda: lo hace quien llama, dentro de su transacción.
 */
trait TieneMaquinaDeEstados
{
    /** @throws AccionFueraDeOrden */
    public function pasarA(BackedEnum $nuevo): void
    {
        if (! $this->estado->puedePasarA($nuevo)) {
            throw AccionFueraDeOrden::transicion($this->estado, $nuevo);
        }

        $this->estado = $nuevo;
    }

    /**
     * Para acciones que no cambian de estado (elegir, revelar, enviar un mensaje):
     * exige estar en alguno de estos estados.
     *
     * @throws AccionFueraDeOrden
     */
    public function exigirEstado(BackedEnum ...$permitidos): void
    {
        if (! in_array($this->estado, $permitidos, true)) {
            $nombres = implode(' o ', array_map(fn (BackedEnum $e) => "«{$e->etiqueta()}»", $permitidos));

            throw new AccionFueraDeOrden("Esto se puede hacer solo en {$nombres} y ahora está en «{$this->estado->etiqueta()}».");
        }
    }
}
