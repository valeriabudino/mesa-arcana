<?php

namespace App\Http\Controllers;

use App\Enums\EstadoSesion;
use App\Enums\TipoTirada;
use App\Models\Sesion;
use App\Models\User;
use App\Services\LecturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SesionController extends Controller
{
    public function __construct(private LecturaService $lectura) {}

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'tipo_tirada' => ['required', Rule::enum(TipoTirada::class)],
            'pregunta' => ['nullable', 'string', 'max:500'],
        ]);

        $sesion = $this->lectura->crear(
            $request->user(),
            TipoTirada::from($datos['tipo_tirada']),
            $datos['pregunta'] ?? null,
        );

        return redirect()->route('sesiones.show', $sesion);
    }

    /**
     * Sin login: formulario "¿Cómo te llamás?".
     * Con login: entra a la mesa (y si es una consultante y la sesión está libre, queda asociada).
     */
    public function show(Request $request, Sesion $sesion): View
    {
        if ($request->user() === null) {
            return view('sesiones.ingresar', ['sesion' => $sesion->load('tarotista')]);
        }

        $sesion = $this->lectura->entrar($sesion, $request->user());

        $sesion->load('tarotista', 'consultante');

        return view('sesiones.mesa', [
            'sesion' => $sesion,
            'config' => $this->configMesa($sesion, $request->user()),
        ]);
    }

    /**
     * Lo que el JS de la mesa necesita para arrancar. Nada de cartas:
     * eso llega por GET /estado, con las mismas reglas que los eventos.
     */
    private function configMesa(Sesion $sesion, User $yo): array
    {
        return [
            'uuid' => $sesion->uuid,
            'yo' => ['id' => $yo->id, 'rol' => $yo->rol->value],
            'tarotista' => $sesion->tarotista->name,
            'tipo_tirada' => $sesion->tipo_tirada->value,
            'posiciones' => $sesion->tipo_tirada->posiciones(),
            'etiquetas' => collect(EstadoSesion::cases())->mapWithKeys(fn (EstadoSesion $e) => [$e->value => $e->etiqueta()]),
            'link' => route('sesiones.show', $sesion),
            'rutas' => [
                'estado' => route('sesiones.estado', $sesion),
                'mezclar' => route('sesiones.mezclar', $sesion),
                'elegir' => route('sesiones.elegir', $sesion),
                'revelar' => route('sesiones.revelar', $sesion),
                'finalizar' => route('sesiones.finalizar', $sesion),
            ],
        ];
    }

    /** La consultante entra solo con su nombre. */
    public function ingresar(Request $request, Sesion $sesion): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:40'],
        ]);

        $invitada = $this->lectura->ingresarInvitada($sesion, $datos['nombre']);

        Auth::login($invitada);
        $request->session()->regenerate();

        return redirect()->route('sesiones.show', $sesion);
    }

    /** Foto de la mesa en JSON: para dibujar al entrar y para redibujar al reconectar. */
    public function estado(Sesion $sesion): JsonResponse
    {
        return response()->json($this->lectura->estado($sesion));
    }

    public function resumen(Sesion $sesion): View
    {
        return view('sesiones.resumen', [
            'sesion' => $sesion,
            'reveladas' => $sesion->cartas()->whereNotNull('revelada_at')->with('carta')->get(),
        ]);
    }
}
