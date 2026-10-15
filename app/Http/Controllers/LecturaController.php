<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Services\ConsultaService;
use App\Services\LecturaCartasService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Acciones de una tirada. Devuelven 204: el navegador NO anima con la respuesta,
 * anima cuando llega el evento por Reverb (en los dos navegadores a la vez).
 * Quién puede cada acción lo controla la policy desde la ruta (->can), y que la
 * lectura sea de esta consulta lo garantiza scopeBindings.
 */
class LecturaController extends Controller
{
    public function __construct(
        private LecturaCartasService $cartas,
        private ConsultaService $consultas,
    ) {}

    public function mezclar(Consulta $consulta, Lectura $lectura): Response
    {
        $this->cartas->mezclar($lectura);

        return response()->noContent();
    }

    public function elegir(Request $request, Consulta $consulta, Lectura $lectura): Response
    {
        $datos = $request->validate([
            'indice_abanico' => ['required', 'integer', 'min:0', 'max:'.(Carta::count() - 1)],
        ]);

        $this->cartas->elegir($lectura, $datos['indice_abanico']);

        return response()->noContent();
    }

    public function revelar(Request $request, Consulta $consulta, Lectura $lectura): Response
    {
        $datos = $request->validate([
            'posicion' => ['required', 'integer', 'min:0', 'max:'.max(0, $lectura->tipo->cantidad() - 1)],
        ]);

        $this->cartas->revelar($lectura, $datos['posicion']);

        return response()->noContent();
    }

    public function cerrar(Consulta $consulta, Lectura $lectura): Response
    {
        $this->consultas->cerrarLectura($lectura);

        return response()->noContent();
    }
}
