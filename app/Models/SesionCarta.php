<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una carta elegida dentro de una sesión.
 *
 * @property int $id
 * @property int $sesion_id
 * @property int $posicion
 * @property int $indice_abanico
 * @property int $carta_id
 * @property bool $invertida
 * @property string|null $nota
 * @property Carbon|null $revelada_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['posicion', 'indice_abanico', 'nota'])] // carta_id e invertida los asigna solo el servidor
#[Hidden(['carta_id', 'invertida', 'carta'])] // red de seguridad: qué carta es se muestra solo a propósito, al revelar
class SesionCarta extends Model
{
    protected $table = 'sesion_cartas';

    protected function casts(): array
    {
        return [
            'invertida' => 'boolean',
            'revelada_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sesion, $this> */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class);
    }

    /** @return BelongsTo<Carta, $this> */
    public function carta(): BelongsTo
    {
        return $this->belongsTo(Carta::class);
    }

    public function estaRevelada(): bool
    {
        return $this->revelada_at !== null;
    }

    /*
    | Las DOS únicas funciones que deciden qué se publica de una carta elegida.
    | Las usan los eventos (CartaElegida, CartaRevelada) y GET /s/{uuid}/estado,
    | así lo que viaja por Reverb y lo que se ve al recargar no pueden divergir.
    */

    /** Elegida y boca abajo: dónde está, nada de qué carta es. */
    public function datosElegida(): array
    {
        return [
            'posicion' => $this->posicion,
            'indice_abanico' => $this->indice_abanico,
        ];
    }

    /** Revelada: recién acá va la carta, con el significado según cómo salió. */
    public function datosRevelada(): array
    {
        $carta = $this->carta;

        return [
            'posicion' => $this->posicion,
            'nombre_posicion' => $this->sesion->tipo_tirada->nombrePosicion($this->posicion),
            'invertida' => $this->invertida,
            'carta' => [
                'nombre' => $carta->nombre,
                'imagen' => $carta->urlImagen(),
                'significado' => $this->invertida ? $carta->significado_invertido : $carta->significado_derecho,
                'palabras_clave' => $carta->palabras_clave,
            ],
        ];
    }
}
