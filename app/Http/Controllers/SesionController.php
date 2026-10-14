<?php

namespace App\Http\Controllers;

use App\Enums\TipoTirada;
use App\Models\Sesion;
use App\Services\LecturaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    /** Entrar a la mesa: la primera consultante que abre el link queda asociada. */
    public function show(Request $request, Sesion $sesion): View
    {
        $sesion = $this->lectura->entrar($sesion, $request->user());

        return view('sesiones.mesa', [
            'sesion' => $sesion->load('tarotista', 'consultante'),
        ]);
    }

    public function resumen(Sesion $sesion): View
    {
        return view('sesiones.resumen', [
            'sesion' => $sesion,
            'reveladas' => $sesion->cartas()->whereNotNull('revelada_at')->with('carta')->get(),
        ]);
    }
}
