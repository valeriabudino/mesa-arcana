<?php

namespace App\Http\Controllers;

use App\Enums\EstadoLectura;
use App\Enums\TipoLectura;
use App\Models\Ciudad;
use App\Models\Consulta;
use App\Models\Tema;
use App\Models\User;
use App\Services\CartaAstralService;
use App\Services\ConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ConsultaController extends Controller
{
    public function __construct(private ConsultaService $consultas) {}

    /** La sala privada. */
    public function show(Request $request, Consulta $consulta): View
    {
        $consulta->load('tarotista', 'consultante', 'turno');

        return view('consultas.sala', [
            'consulta' => $consulta,
            'config' => $this->configSala($consulta, $request->user()),
        ]);
    }

    public function finalizar(Consulta $consulta): Response
    {
        $this->consultas->finalizar($consulta);

        return response()->noContent();
    }

    public function ausente(Consulta $consulta): Response
    {
        $this->consultas->marcarAusente($consulta);

        return response()->noContent();
    }

    /** Resumen imprimible: las lecturas con SOLO las cartas reveladas, y la conversación. */
    public function resumen(Consulta $consulta): View
    {
        $consulta->load([
            'tarotista', 'consultante',
            'lecturas.cartas' => fn ($q) => $q->whereNotNull('revelada_at')->with('carta'),
            'lecturas.pregunta.tema',
            'mensajes.autora',
        ]);

        return view('consultas.resumen', ['consulta' => $consulta]);
    }

    /** Foto de la consulta en JSON: para dibujar al entrar y para redibujar al reconectar. */
    public function estado(Request $request, Consulta $consulta): JsonResponse
    {
        $estado = $this->consultas->estado($consulta);

        // El borrador de la interpretación es privado: solo lo recibe la tarotista (al recargar).
        $lectura = $consulta->lecturaActiva;
        if ($consulta->esTarotista($request->user()) && $lectura?->interpretacion_borrador && ! $lectura->interpretacion_publicada_at) {
            $estado['borrador'] = ['lectura' => $lectura->id, 'texto' => $lectura->interpretacion_borrador, 'terminado' => true, 'fuente' => $lectura->interpretacion_fuente];
        }

        return response()->json($estado);
    }

    /**
     * Lo que el JS de la sala necesita para arrancar. Nada de cartas:
     * eso llega por GET /estado, con las mismas reglas que los eventos.
     */
    private function configSala(Consulta $consulta, User $yo): array
    {
        return [
            'uuid' => $consulta->uuid,
            'yo' => ['id' => $yo->id, 'rol' => $yo->rol->value, 'nombre' => $yo->name],
            'tomado_at' => $consulta->turno->tomado_at->toIso8601String(),
            'minutos_ausente' => ConsultaService::MINUTOS_PARA_AUSENTE,
            'tarotista' => $consulta->tarotista->name,
            'consultante' => $consulta->consultante->name,
            'tipos' => collect(TipoLectura::cases())->mapWithKeys(fn (TipoLectura $t) => [
                $t->value => ['nombre' => $t->nombre(), 'posiciones' => $t->posiciones(), 'aclaratorias' => $t->admiteAclaratorias()],
            ]),
            // Para el formulario de nacimiento: provincia → ciudad (datos públicos).
            'ciudades' => Ciudad::orderBy('provincia')->orderBy('nombre')->get(['id', 'nombre', 'provincia']),
            'puntos' => CartaAstralService::PUNTOS,
            // Para pedir una lectura: tema → pregunta predeterminada (plan v3 §13.1).
            'temas' => Tema::paraElFormulario(),
            'etiquetas' => collect(EstadoLectura::cases())->mapWithKeys(fn (EstadoLectura $e) => [$e->value => $e->etiqueta()]),
            'rutas' => [
                'estado' => route('consultas.estado', $consulta),
                'mensajes' => route('consultas.mensajes', $consulta),
                'pedidos' => route('consultas.pedidos', $consulta),
                'finalizar' => route('consultas.finalizar', $consulta),
                'ausente' => route('consultas.ausente', $consulta),
                'atencion' => route('atencion'),
                'espera' => route('espera'),
                // Las de la lectura activa: el JS arma {lecturas}/{id}/mezclar|elegir|revelar.
                'lecturas' => route('consultas.show', $consulta).'/lecturas',
            ],
        ];
    }
}
