<?php

namespace App\Http\Controllers;

use App\Models\Ciudad;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Services\CartaAstralService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** La carta astral: datos de nacimiento (consultante) y señalar un punto (tarotista). */
class CartaAstralController extends Controller
{
    public function __construct(private CartaAstralService $astral) {}

    public function nacimiento(Request $request, Consulta $consulta, Lectura $lectura): Response
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'hora_desconocida' => ['boolean'],
            'hora' => ['exclude_if:hora_desconocida,true', 'required', 'date_format:H:i'],
            'ciudad_id' => ['required', 'integer', Rule::exists('ciudades', 'id')],
        ]);

        $this->astral->guardarDatos($lectura, $datos['fecha'], $datos['hora'] ?? null, Ciudad::findOrFail($datos['ciudad_id']));

        return response()->noContent();
    }

    public function senalar(Request $request, Consulta $consulta, Lectura $lectura): Response
    {
        $datos = $request->validate(['planeta' => ['required', Rule::in(array_keys(CartaAstralService::PUNTOS))]]);

        $this->astral->senalar($lectura, $datos['planeta']);

        return response()->noContent();
    }
}
