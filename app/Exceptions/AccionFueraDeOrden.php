<?php

namespace App\Exceptions;

use BackedEnum;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Se lanza cuando una acción no corresponde al estado actual (de un turno, una
 * consulta o una lectura): mezclar dos veces, elegir mientras se revela, etc. → 409.
 */
class AccionFueraDeOrden extends RuntimeException
{
    /** Los estados son enums con MaquinaDeEstados (tienen etiqueta()). */
    public static function transicion(BackedEnum $actual, BackedEnum $nuevo): self
    {
        return new self("Está en «{$actual->etiqueta()}» y no puede pasar a «{$nuevo->etiqueta()}».");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
