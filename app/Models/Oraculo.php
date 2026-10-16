<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El oráculo del día (plan v3 §13.5): una carta al azar por día, para cualquiera que
 * entre al inicio, sin login. La elige OraculoService::revelar(), nunca antes.
 *
 * @property int $id
 * @property Carbon $fecha
 * @property int $carta_id
 * @property bool $invertida
 * @property string $mensaje
 * @property Carbon $revelado_at
 * @property-read Carta $carta
 */
class Oraculo extends Model
{
    protected function casts(): array
    {
        return ['fecha' => 'date', 'invertida' => 'boolean', 'revelado_at' => 'datetime'];
    }

    /** @return BelongsTo<Carta, $this> */
    public function carta(): BelongsTo
    {
        return $this->belongsTo(Carta::class);
    }

    /**
     * Lo que ve cualquiera en el inicio (y viaja por el canal público).
     *
     * @return array{fecha: string, carta: array{nombre: string, imagen: string, palabras_clave: list<string>}, invertida: bool, mensaje: string}
     */
    public function datosPublicos(): array
    {
        return [
            'fecha' => $this->fecha->toDateString(),
            'carta' => ['nombre' => $this->carta->nombre, 'imagen' => $this->carta->urlImagen(), 'palabras_clave' => $this->carta->palabras_clave],
            'invertida' => $this->invertida,
            'mensaje' => $this->mensaje,
        ];
    }
}
