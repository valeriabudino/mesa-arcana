<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoLectura;
use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\MazoMezclado;
use App\Exceptions\AccionFueraDeOrden;
use App\Models\Carta;
use App\Models\Lectura;
use App\Models\LecturaCarta;
use App\Services\Concerns\EmiteEventos;
use Illuminate\Support\Facades\DB;

/**
 * Toda la lógica de una tirada de cartas. Patrón de cada acción:
 *   1. DB::transaction + lockForUpdate sobre la fila de la lectura (el "molinete").
 *   2. Validar el estado, modificar y guardar.
 *   3. Recién después del commit, emitir el evento por Reverb (canal consulta.{uuid}).
 */
class LecturaCartasService
{
    use EmiteEventos;

    private const ORDINALES = ['primera', 'segunda', 'tercera', 'cuarta', 'quinta', 'sexta', 'séptima', 'octava', 'novena', 'décima'];

    public function __construct(private ChatService $chat) {}

    /**
     * Mezcla en el servidor (Lista → Eligiendo). El orden real queda en orden_mazo ($hidden).
     */
    public function mezclar(Lectura $lectura): Lectura
    {
        $lectura = DB::transaction(function () use ($lectura) {
            $l = $this->bloquear($lectura);

            $l->pasarA(EstadoLectura::Eligiendo);
            $l->orden_mazo = $this->mezclarIds(Carta::pluck('id')->all());
            $l->save();

            return $l;
        });

        // Semilla SOLO visual: un número aparte, sin relación con orden_mazo.
        $this->emitir(new MazoMezclado($lectura->consulta->uuid, $lectura->id, random_int(1, 2_147_483_647)));
        $this->chat->sistema($lectura->consulta, "{$lectura->consulta->tarotista->name} mezcló el mazo.");

        return $lectura;
    }

    /**
     * La consultante toca la carta $indiceAbanico (0..77) del abanico.
     * Si es la última de la tirada, pasa a Revelando en la misma transacción.
     */
    public function elegir(Lectura $lectura, int $indiceAbanico): LecturaCarta
    {
        [$lectura, $elegida] = DB::transaction(function () use ($lectura, $indiceAbanico) {
            $l = $this->bloquear($lectura);
            $l->exigirEstado(EstadoLectura::Eligiendo);

            if ($l->cartas()->where('indice_abanico', $indiceAbanico)->exists()) {
                throw new AccionFueraDeOrden('Esa carta ya fue elegida.');
            }

            $posicion = $l->cartas()->count();

            $elegida = new LecturaCarta(['posicion' => $posicion, 'indice_abanico' => $indiceAbanico]);
            $elegida->lectura_id = $l->id;
            $elegida->carta_id = $l->orden_mazo[$indiceAbanico]; // mismo orden de siempre: no se remezcla
            $elegida->invertida = random_int(0, 1) === 1;
            if ($l->aclarando !== null) {
                $elegida->es_aclaratoria = true;
                $elegida->aclara_posicion = $l->aclarando;
                $l->aclarando = null;
            }
            $elegida->save();

            // Ya no falta ninguna: a revelar. (cartas_esperadas = tirada + aclaratorias aceptadas)
            if ($posicion + 1 === $l->cartas_esperadas) {
                $l->pasarA(EstadoLectura::Revelando);
            }
            $l->save();

            return [$l, $elegida];
        });

        $this->emitir(new CartaElegida($lectura->consulta->uuid, $elegida));
        // Ojo: la bitácora NUNCA nombra la carta elegida (sería otra puerta de atrás).
        $ordinal = self::ORDINALES[$elegida->posicion] ?? ($elegida->posicion + 1).'ª';
        $this->chat->sistema($lectura->consulta, "{$lectura->consulta->consultante->name} eligió su {$ordinal} carta.");

        return $elegida;
    }

    /**
     * La tarotista da vuelta la carta de una posición (en cualquier orden).
     * Recién acá el evento lleva la carta. Con la última, la lectura queda Abierta.
     */
    public function revelar(Lectura $lectura, int $posicion): LecturaCarta
    {
        [$lectura, $carta] = DB::transaction(function () use ($lectura, $posicion) {
            $l = $this->bloquear($lectura);
            $l->exigirEstado(EstadoLectura::Revelando);

            $carta = $l->cartas()->where('posicion', $posicion)->firstOrFail();

            if ($carta->estaRevelada()) {
                throw new AccionFueraDeOrden('Esa carta ya está revelada.');
            }

            $carta->revelada_at = now();
            $carta->save();

            if (! $l->cartas()->whereNull('revelada_at')->exists()) {
                $l->pasarA(EstadoLectura::Abierta);
                $l->save();
            }

            return [$l, $carta];
        });

        $this->emitir(new CartaRevelada($lectura->consulta->uuid, $carta->load('carta')->setRelation('lectura', $lectura)));
        // La carta entra al chat con su significado. El navegador la muestra recién cuando
        // termina de girar en la mesa (mesa.js la encola detrás de la animación).
        $this->chat->carta($carta);

        return $carta;
    }

    /**
     * La tarotista aceptó una aclaratoria (Abierta → Eligiendo, una carta más).
     * Se llama dentro de la transacción de ConsultaService::aceptarPedido.
     */
    public function aceptarAclaratoria(Lectura $lectura, int $aclara): Lectura
    {
        $l = $this->bloquear($lectura);
        if ($motivo = $l->motivoParaNoAclarar($aclara)) {
            throw new AccionFueraDeOrden($motivo);
        }

        $l->cartas_esperadas++;
        $l->aclarando = $aclara;
        $l->pasarA(EstadoLectura::Eligiendo);
        $l->save();

        return $l;
    }

    /**
     * Foto de la lectura para GET /c/{uuid}/estado. Mismas reglas que los eventos:
     * una carta sin revelar NO dice qué carta es.
     *
     * @return array{id: int, tipo: string, estado: string, pregunta: string|null, cartas: list<array<string, mixed>>}
     */
    public function foto(Lectura $lectura): array
    {
        $lectura->load('cartas');

        return $lectura->datosPublicos() + [
            'aclarando' => $lectura->aclarando,
            'cartas' => $lectura->cartas->map(function (LecturaCarta $elegida) use ($lectura) {
                if (! $elegida->estaRevelada()) {
                    return ['revelada' => false] + $elegida->datosElegida();
                }

                // Solo para las reveladas se trae la carta de la base: lo que no se manda, ni se carga.
                // indice_abanico ya viajó público en CartaElegida; hace falta para redibujar.
                $elegida->setRelation('lectura', $lectura)->load('carta');

                return ['revelada' => true] + $elegida->datosElegida() + $elegida->datosRevelada();
            })->all(),
        ];
    }

    /**
     * Relee la lectura con SELECT … FOR UPDATE: cualquier otra acción sobre
     * esta lectura espera hasta que la transacción actual termine.
     * Además, nada se mueve si la consulta ya terminó.
     */
    private function bloquear(Lectura $lectura): Lectura
    {
        $l = Lectura::whereKey($lectura->id)->lockForUpdate()->firstOrFail();
        $l->consulta->exigirEstado(EstadoConsulta::Activa);

        return $l;
    }

    /**
     * Fisher-Yates con random_int (criptográficamente seguro).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function mezclarIds(array $ids): array
    {
        for ($i = count($ids) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }

        return $ids;
    }
}
