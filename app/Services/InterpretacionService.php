<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoLectura;
use App\Events\BorradorInterpretacion;
use App\Exceptions\AccionFueraDeOrden;
use App\Interpretacion\Interpretador;
use App\Interpretacion\InterpretadorBase;
use App\Models\Lectura;
use App\Models\LecturaCarta;
use App\Models\Mensaje;
use App\Services\Concerns\EmiteEventos;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * La interpretación de una lectura de cartas (plan v3 §13.6):
 * se genera en un job cuando quedan todas las cartas a la vista, le llega a la tarotista
 * como borrador por su canal PRIVADO (en vivo, mientras se escribe), ella lo edita y lo
 * publica en el chat de la consulta.
 */
class InterpretacionService
{
    use EmiteEventos;

    public function __construct(
        private Interpretador $interpretador,
        private InterpretadorBase $base,
        private ChatService $chat,
    ) {}

    /**
     * Lo ÚNICO que se le pasa al interpretador: nada de nombres, emails ni datos personales.
     *
     * @return array{tipo: string, tema: string|null, pregunta: string|null, cartas: list<array{posicion: string, nombre: string, invertida: bool, significado: string, aclara: string|null}>, respuesta: string|null}
     */
    public function datosDe(Lectura $lectura): array
    {
        $lectura->loadMissing(['pregunta.tema', 'cartas.carta']);
        $cartas = $lectura->cartas->filter(fn (LecturaCarta $c) => $c->estaRevelada())->sortBy('posicion')->values();
        $respuesta = null;

        return [
            'tipo' => $lectura->tipo->nombre(),
            'tema' => $lectura->pregunta?->tema->nombre,
            'pregunta' => $lectura->pregunta?->texto,
            'cartas' => $cartas->map(function (LecturaCarta $c) use ($lectura, &$respuesta) {
                $revelada = $c->setRelation('lectura', $lectura)->datosRevelada();
                $respuesta ??= $revelada['respuesta']['texto'] ?? null;

                return [
                    'posicion' => $revelada['nombre_posicion'],
                    'nombre' => $revelada['carta']['nombre'],
                    'invertida' => $revelada['invertida'],
                    'significado' => $revelada['carta']['significado'],
                    'aclara' => $c->aclara_posicion === null ? null : $lectura->tipo->nombrePosicion($c->aclara_posicion),
                ];
            })->all(),
            'respuesta' => $respuesta,
        ];
    }

    /** La clave de la caché: la misma pregunta con las mismas cartas, igual orientadas. */
    public function claveCache(array $datos): string
    {
        return 'interpretacion:'.sha1(json_encode([$datos['tipo'], $datos['pregunta'], array_map(
            fn ($c) => [$c['posicion'], $c['nombre'], $c['invertida'], $c['aclara']],
            $datos['cartas'],
        )]));
    }

    /** Escribe el borrador (lo corre el job GenerarInterpretacion). */
    public function generar(Lectura $lectura): void
    {
        if ($lectura->estado !== EstadoLectura::Abierta) {
            return; // se cerró (o volvió a eligiendo por una aclaratoria) mientras esperaba en la cola
        }

        $datos = $this->datosDe($lectura);
        $clave = $this->claveCache($datos);
        $avisar = fn (string $texto, bool $terminado, string $fuente) => $this->emitir(
            new BorradorInterpretacion($lectura, $texto, $terminado, $fuente),
        );

        if ($guardada = Cache::get($clave)) {
            [$texto, $fuente] = [$guardada, 'ia'];
        } else {
            try {
                $texto = $this->interpretador->interpretar($datos, fn (string $parcial) => $avisar($parcial, false, $this->interpretador->fuente()));
                $fuente = $this->interpretador->fuente();
                if ($fuente === 'ia') {
                    Cache::put($clave, $texto, now()->addDays(config('interpretacion.cache_dias')));
                }
            } catch (Throwable $e) {
                report($e); // la API falló o tardó: la base nunca falla
                $texto = $this->base->interpretar($datos, fn () => null);
                $fuente = $this->base->fuente();
            }
        }

        $lectura->interpretacion_borrador = $texto;
        $lectura->interpretacion_fuente = $fuente;
        $lectura->save();
        $avisar($texto, true, $fuente);
    }

    /** La tarotista publica el borrador (editado o no): queda en el chat de la consulta. */
    public function publicar(Lectura $lectura, string $texto): Mensaje
    {
        $lectura->consulta->exigirEstado(EstadoConsulta::Activa);
        if ($lectura->estado !== EstadoLectura::Abierta && $lectura->estado !== EstadoLectura::Cerrada) {
            throw new AccionFueraDeOrden('La interpretación se publica con todas las cartas a la vista.');
        }

        $lectura->interpretacion_publicada_at = now();
        $lectura->save();

        return $this->chat->interpretacion($lectura, $texto);
    }
}
