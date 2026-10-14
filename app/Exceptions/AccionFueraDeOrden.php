<?php

namespace App\Exceptions;

use App\Enums\EstadoSesion;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Se lanza cuando una acción no corresponde al estado actual de la sesión
 * (mezclar dos veces, elegir mientras se revela, etc.). Se responde con 409.
 */
class AccionFueraDeOrden extends RuntimeException
{
    public static function transicion(EstadoSesion $actual, EstadoSesion $nuevo): self
    {
        return new self("La sesión está en «{$actual->etiqueta()}» y no puede pasar a «{$nuevo->etiqueta()}».");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
