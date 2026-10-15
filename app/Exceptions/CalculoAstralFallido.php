<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** El script de cálculo falló o devolvió algo raro. La lectura sigue esperando datos: se puede reintentar. */
class CalculoAstralFallido extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'No se pudo calcular la carta. Probá de nuevo en un momento.'], 503);
    }
}
