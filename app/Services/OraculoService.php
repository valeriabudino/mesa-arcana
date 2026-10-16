<?php

namespace App\Services;

use App\Events\OraculoRevelado;
use App\Models\Carta;
use App\Models\Oraculo;
use App\Services\Concerns\EmiteEventos;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * El oráculo del día (plan v3 §13.5). La carta se elige recién al revelar, con
 * random_int: no existe antes, así que no hay forma de adivinarla mirando la red.
 */
class OraculoService
{
    use EmiteEventos;

    /** El día y la hora del oráculo son los de Argentina (la app guarda todo en UTC). */
    public const ZONA = 'America/Argentina/Buenos_Aires';

    public const HORA = '09:00';

    public function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONA);
    }

    /** El oráculo de hoy, si ya se reveló. */
    public function deHoy(): ?Oraculo
    {
        return Oraculo::with('carta')->whereDate('fecha', $this->ahora()->toDateString())->first();
    }

    /**
     * El de hoy, revelándolo si ya pasó la hora y nadie lo hizo (por ejemplo, si no está
     * corriendo schedule:work). Antes de las 9 devuelve null: la carta todavía no existe.
     */
    public function deHoyOPendiente(): ?Oraculo
    {
        if ($oraculo = $this->deHoy()) {
            return $oraculo;
        }
        if ($this->ahora()->lt($this->ahora()->setTimeFromTimeString(self::HORA)) || ! Carta::exists()) {
            return null; // todavía no es la hora (o la base no tiene las cartas cargadas)
        }

        return $this->revelar()[0];
    }

    /** Cuándo es la próxima revelación (hoy a las 9 si todavía no pasó, si no mañana). */
    public function proximaRevelacion(): CarbonImmutable
    {
        $hoy = $this->ahora()->setTimeFromTimeString(self::HORA);

        return $this->ahora()->lt($hoy) ? $hoy : $hoy->addDay();
    }

    /**
     * Revela la carta de hoy. Si ya estaba revelada no hace nada, salvo con $otra
     * (para la demo: vuelve a sortear la de hoy).
     *
     * @return array{0: Oraculo, 1: bool} el oráculo y si se reveló recién
     */
    public function revelar(bool $otra = false): array
    {
        $existente = $this->deHoy();
        if ($existente && ! $otra) {
            return [$existente, false];
        }

        $ids = Carta::pluck('id')->all();
        if ($ids === []) {
            throw new RuntimeException('No hay cartas cargadas: corré «sail artisan db:seed».');
        }
        $carta = Carta::findOrFail($ids[random_int(0, count($ids) - 1)]);
        $invertida = random_int(0, 1) === 1;

        $oraculo = $existente ?? new Oraculo;
        $oraculo->fecha = $this->ahora()->startOfDay();
        $oraculo->carta_id = $carta->id;
        $oraculo->invertida = $invertida;
        $oraculo->mensaje = $invertida ? $carta->significado_invertido : $carta->significado_derecho;
        $oraculo->revelado_at = now();
        $oraculo->save();
        $oraculo->setRelation('carta', $carta);

        $this->emitir(new OraculoRevelado($oraculo));

        return [$oraculo, true];
    }
}
