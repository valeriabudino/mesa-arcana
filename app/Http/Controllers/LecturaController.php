<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use App\Models\Sesion;
use App\Services\LecturaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Acciones de la mesa. Devuelven 204: el navegador NO anima con la respuesta,
 * anima cuando llega el evento por Reverb (en los dos navegadores a la vez).
 * Quién puede cada acción lo controla la policy desde la ruta (->can).
 */
class LecturaController extends Controller
{
    public function __construct(private LecturaService $lectura) {}

    public function mezclar(Sesion $sesion): Response
    {
        $this->lectura->mezclar($sesion);

        return response()->noContent();
    }

    public function elegir(Request $request, Sesion $sesion): Response
    {
        $datos = $request->validate([
            'indice_abanico' => ['required', 'integer', 'min:0', 'max:'.(Carta::count() - 1)],
        ]);

        $this->lectura->elegir($sesion, $datos['indice_abanico']);

        return response()->noContent();
    }

    public function revelar(Request $request, Sesion $sesion): Response
    {
        $datos = $request->validate([
            'posicion' => ['required', 'integer', 'min:0', 'max:'.($sesion->tipo_tirada->cantidad() - 1)],
        ]);

        $this->lectura->revelar($sesion, $datos['posicion']);

        return response()->noContent();
    }

    public function finalizar(Sesion $sesion): Response
    {
        $this->lectura->finalizar($sesion);

        return response()->noContent();
    }
}
