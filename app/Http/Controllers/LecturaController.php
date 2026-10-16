<?php

namespace App\Http\Controllers;

use App\Models\Carta;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Services\ChatService;
use App\Services\ConsultaService;
use App\Services\InterpretacionService;
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
        private ChatService $chat,
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
            'posicion' => ['required', 'integer', 'min:0', 'max:'.max(0, $lectura->cartas_esperadas - 1)],
        ]);

        $this->cartas->revelar($lectura, $datos['posicion']);

        return response()->noContent();
    }

    public function cerrar(Consulta $consulta, Lectura $lectura): Response
    {
        $this->consultas->cerrarLectura($lectura);

        return response()->noContent();
    }

    /** La consultante pide aclarar una carta (queda como pedido en el chat). */
    public function pedirAclaratoria(Request $request, Consulta $consulta, Lectura $lectura): Response
    {
        // Sin texto libre (plan v3 §13.1): se aclara la carta de esa posición.
        $datos = $request->validate(['posicion' => ['required', 'integer', 'min:0']]);

        $this->chat->pedirAclaratoria($lectura, $request->user(), $datos['posicion']);

        return response()->noContent();
    }

    /** La tarotista publica la interpretación (el borrador de la IA, editado o no). */
    public function publicarInterpretacion(Request $request, Consulta $consulta, Lectura $lectura, InterpretacionService $interpretaciones): Response
    {
        $datos = $request->validate(['texto' => ['required', 'string', 'max:4000']]);

        $interpretaciones->publicar($lectura, trim($datos['texto']));

        return response()->noContent();
    }
}
