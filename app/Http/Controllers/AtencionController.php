<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Services\ColaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** La tarotista: ver la fila en vivo, ponerse disponible y atender. */
class AtencionController extends Controller
{
    public function __construct(private ColaService $cola) {}

    public function show(Request $request): View
    {
        $consulta = $this->cola->consultaActiva($request->user());

        return view('atencion.panel', [
            'config' => [
                'yo' => $request->user()->name,
                'disponible' => $request->user()->disponible,
                'consulta_activa' => $consulta ? route('consultas.show', $consulta) : null,
                'rutas' => [
                    'cola' => route('atencion.cola'),
                    'disponible' => route('atencion.disponible'),
                    'tomar' => url('turnos'), // + /{uuid}/tomar
                ],
            ],
        ]);
    }

    /** La fila completa, con los mismos datos que TurnoCreado (al entrar y al reconectar). */
    public function cola(): JsonResponse
    {
        return response()->json($this->cola->colaParaTarotistas());
    }

    public function disponible(Request $request): Response
    {
        $datos = $request->validate(['disponible' => ['required', 'boolean']]);
        $this->cola->cambiarDisponible($request->user(), $datos['disponible']);

        return response()->noContent();
    }

    /** Devuelve a dónde ir: la sala de la consulta recién creada. */
    public function tomar(Request $request, Turno $turno): JsonResponse
    {
        $consulta = $this->cola->tomar($turno, $request->user());

        return response()->json(['url' => route('consultas.show', $consulta)]);
    }
}
