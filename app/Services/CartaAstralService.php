<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoLectura;
use App\Enums\Signo;
use App\Events\CartaAstralCalculada;
use App\Events\DatosNacimientoRecibidos;
use App\Events\PlanetaSenalado;
use App\Exceptions\AccionFueraDeOrden;
use App\Exceptions\CalculoAstralFallido;
use App\Models\Ciudad;
use App\Models\Lectura;
use App\Services\Concerns\EmiteEventos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

/**
 * La carta astral (versión tríada): Sol, Luna y Ascendente.
 *
 *   1. Hora local → UTC con la zona horaria IANA de la ciudad, que conoce TODOS los
 *      cambios de horario históricos de esa provincia (ej.: enero de 1990 en Formosa
 *      era UTC−2, no UTC−3).
 *   2. El cálculo lo hace resources/astro/calcular.mjs (astronomy-engine), ejecutado
 *      con Process::run([...]): un array, sin shell, y solo con datos del servidor.
 *   3. Se guarda con lock y se avisa por Reverb (el servidor calcula una vez; las dos
 *      pantallas muestran exactamente lo mismo).
 */
class CartaAstralService
{
    use EmiteEventos;

    public const PUNTOS = ['sol' => 'el Sol', 'luna' => 'la Luna', 'ascendente' => 'el Ascendente'];

    public function __construct(private ChatService $chat) {}

    /**
     * @param  string|null  $hora  "HH:MM", o null si no la sabe (sin Ascendente)
     */
    public function guardarDatos(Lectura $lectura, string $fecha, ?string $hora, Ciudad $ciudad): Lectura
    {
        // Chequeo rápido antes de calcular (el definitivo es con lock, más abajo).
        $lectura->consulta->exigirEstado(EstadoConsulta::Activa);
        $lectura->exigirEstado(EstadoLectura::EsperandoDatos);

        $horaConocida = $hora !== null;
        $utc = $this->instanteLocal($fecha, $hora ?? '12:00', $ciudad->zona_horaria)->utc();

        // El cálculo corre FUERA de la transacción: no se retiene el lock mientras corre node.
        $resultado = $this->armarResultado($this->calcular($utc, $ciudad->lat, $ciudad->lng, $horaConocida), $horaConocida);

        $lectura = DB::transaction(function () use ($lectura, $fecha, $hora, $ciudad, $utc, $resultado) {
            $l = Lectura::whereKey($lectura->id)->lockForUpdate()->firstOrFail();
            $l->consulta->exigirEstado(EstadoConsulta::Activa);
            $l->exigirEstado(EstadoLectura::EsperandoDatos); // si llegaron dos envíos a la vez, el segundo da 409

            $l->datos_nacimiento = ['fecha' => $fecha, 'hora' => $hora, 'ciudad_id' => $ciudad->id, 'utc' => $utc->toIso8601ZuluString()];
            $l->resultado = $resultado;
            $l->pasarA(EstadoLectura::Calculada);
            $l->save();

            return $l;
        });

        $uuid = $lectura->consulta->uuid;
        $this->emitir(new DatosNacimientoRecibidos($uuid, $lectura->id));
        $this->emitir(new CartaAstralCalculada($uuid, $lectura->id, $resultado));
        $this->chat->cartaAstral($lectura);

        return $lectura;
    }

    /** La tarotista señala un punto: foco y cámara en las dos pantallas. */
    public function senalar(Lectura $lectura, string $planeta): void
    {
        $lectura->consulta->exigirEstado(EstadoConsulta::Activa);
        $lectura->exigirEstado(EstadoLectura::Calculada);
        $punto = $lectura->resultado['puntos'][$planeta] ?? throw new AccionFueraDeOrden('Ese punto no está en la carta.');

        $this->emitir(new PlanetaSenalado($lectura->consulta->uuid, $lectura->id, $planeta));
        $this->chat->sistema($lectura->consulta, "{$lectura->consulta->tarotista->name} señaló ".self::PUNTOS[$planeta]." en {$punto['signo_nombre']}.");
    }

    /**
     * La fecha y hora de nacimiento, en la zona horaria (histórica) de la ciudad.
     * Rechaza las horas que no existieron: el día que se adelantaba el reloj se saltaba una hora.
     */
    public function instanteLocal(string $fecha, string $hora, string $zonaHoraria): CarbonImmutable
    {
        $local = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$fecha} {$hora}", $zonaHoraria);

        // Si la hora cayó en el "salto", PHP la corre: si no coincide con lo escrito, no existió.
        if ($local === null || $local->format('Y-m-d H:i') !== "{$fecha} {$hora}") {
            throw ValidationException::withMessages([
                'hora' => 'Esa hora no existió en esa provincia: ese día se adelantó el reloj. Revisá la hora.',
            ]);
        }

        return $local;
    }

    /**
     * @return array{sol: float, luna: float, ascendente: float|null}
     */
    private function calcular(CarbonImmutable $utc, float $lat, float $lng, bool $horaConocida): array
    {
        // Array → cada dato es un argumento, nunca código. Y son todos generados acá.
        $proceso = Process::path(base_path())->timeout(15)->run([
            'node', 'resources/astro/calcular.mjs',
            $utc->toIso8601ZuluString(), (string) $lat, (string) $lng, $horaConocida ? '1' : '0',
        ]);

        $datos = $proceso->successful() ? json_decode($proceso->output(), true) : null;
        $enRango = fn ($v) => is_numeric($v) && $v >= 0 && $v < 360;

        if (! is_array($datos) || ! $enRango($datos['sol'] ?? null) || ! $enRango($datos['luna'] ?? null)
            || ($horaConocida ? ! $enRango($datos['ascendente'] ?? null) : ($datos['ascendente'] ?? null) !== null)) {
            report(new CalculoAstralFallido('Salida inesperada de calcular.mjs: '.$proceso->output().$proceso->errorOutput()));

            throw new CalculoAstralFallido;
        }

        return $datos;
    }

    /**
     * Lo que se guarda y viaja en CartaAstralCalculada: por punto, signo, grado e interpretación.
     *
     * @param  array{sol: float, luna: float, ascendente: float|null}  $posiciones
     * @return array{hora_conocida: bool, puntos: array<string, array<string, mixed>>}
     */
    private function armarResultado(array $posiciones, bool $horaConocida): array
    {
        $textos = require resource_path('astro/interpretaciones.php');
        $puntos = [];

        foreach (array_keys(self::PUNTOS) as $punto) {
            $longitud = $posiciones[$punto];
            if ($longitud === null) {
                continue; // sin hora no hay Ascendente
            }

            $signo = Signo::deLongitud($longitud);
            $puntos[$punto] = [
                'longitud' => round($longitud, 4),
                'signo' => $signo->value,
                'signo_nombre' => $signo->nombre(),
                'simbolo' => $signo->simbolo(),
                'grado' => Signo::gradoEnSigno($longitud),
                'interpretacion' => $textos[$punto][$signo->value],
                'aproximada' => $punto === 'luna' && ! $horaConocida, // la Luna avanza ~13° por día
            ];
        }

        return ['hora_conocida' => $horaConocida, 'puntos' => $puntos];
    }
}
