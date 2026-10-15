<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Services\ColaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** La consultante: pedir turno, esperar y (si quiere) salir de la fila. */
class EsperaController extends Controller
{
    public function __construct(private ColaService $cola) {}

    /**
     * Sin turno: el formulario "¿Cómo te llamás?". Con turno: la sala de espera.
     * Si ya la atendieron, directo a la consulta.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user?->esTarotista()) {
            return redirect()->route('atencion');
        }
        if ($user && ($consulta = $this->cola->consultaActiva($user))) {
            return redirect()->route('consultas.show', $consulta);
        }

        $turno = $user ? $this->cola->turnoVigente($user) : null;

        return $turno
            ? view('espera.sala', ['turno' => $turno, 'config' => $this->config($request)])
            : view('espera.pedir', ['conNombre' => $user === null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user?->esTarotista(), 403);

        $datos = $request->validate([
            'nombre' => [$user ? 'exclude' : 'required', 'string', 'max:40'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ]);

        if ($user === null) {
            $user = $this->cola->crearInvitada($datos['nombre']);
            Auth::login($user);
            $request->session()->regenerate();
        }

        $this->cola->pedirTurno($user, $datos['motivo'] ?? null);

        return redirect()->route('espera');
    }

    /** Foto de la fila (anónima) y del turno propio: al entrar y al reconectar. */
    public function estado(Request $request): JsonResponse
    {
        return response()->json($this->cola->estadoEspera($request->user()));
    }

    public function cancelar(Turno $turno): RedirectResponse
    {
        $this->cola->cancelar($turno);

        return redirect()->route('espera');
    }

    private function config(Request $request): array
    {
        return [
            'yo' => $request->user()->id,
            'rutas' => ['estado' => route('espera.estado')],
        ];
    }
}
