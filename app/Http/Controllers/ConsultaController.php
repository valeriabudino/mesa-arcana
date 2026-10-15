<?php

namespace App\Http\Controllers;

use App\Enums\EstadoLectura;
use App\Enums\TipoLectura;
use App\Models\Consulta;
use App\Models\User;
use App\Services\ConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsultaController extends Controller
{
    public function __construct(private ConsultaService $consultas) {}

    /** La sala privada. */
    public function show(Request $request, Consulta $consulta): View
    {
        $consulta->load('tarotista', 'consultante');

        return view('consultas.sala', [
            'consulta' => $consulta,
            'config' => $this->configSala($consulta, $request->user()),
        ]);
    }

    /** Foto de la consulta en JSON: para dibujar al entrar y para redibujar al reconectar. */
    public function estado(Consulta $consulta): JsonResponse
    {
        return response()->json($this->consultas->estado($consulta));
    }

    /**
     * Lo que el JS de la sala necesita para arrancar. Nada de cartas:
     * eso llega por GET /estado, con las mismas reglas que los eventos.
     */
    private function configSala(Consulta $consulta, User $yo): array
    {
        return [
            'uuid' => $consulta->uuid,
            'yo' => ['id' => $yo->id, 'rol' => $yo->rol->value],
            'tarotista' => $consulta->tarotista->name,
            'consultante' => $consulta->consultante->name,
            'tipos' => collect(TipoLectura::cases())->mapWithKeys(fn (TipoLectura $t) => [
                $t->value => ['nombre' => $t->nombre(), 'posiciones' => $t->posiciones()],
            ]),
            'etiquetas' => collect(EstadoLectura::cases())->mapWithKeys(fn (EstadoLectura $e) => [$e->value => $e->etiqueta()]),
            'rutas' => [
                'estado' => route('consultas.estado', $consulta),
                // Las de la lectura activa: el JS arma {lecturas}/{id}/mezclar|elegir|revelar.
                'lecturas' => route('consultas.show', $consulta).'/lecturas',
            ],
        ];
    }
}
