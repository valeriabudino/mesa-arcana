<?php

namespace App\Http\Controllers;

use App\Enums\TipoTirada;
use App\Models\Sesion;
use App\Services\LecturaService;
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

        return view('sesiones.mesa', [
            'sesion' => $sesion->load('tarotista', 'consultante'),
        ]);
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

    public function resumen(Sesion $sesion): View
    {
        return view('sesiones.resumen', [
            'sesion' => $sesion,
            'reveladas' => $sesion->cartas()->whereNotNull('revelada_at')->with('carta')->get(),
        ]);
    }
}
