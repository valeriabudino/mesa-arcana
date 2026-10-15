<?php

namespace App\Models;

use App\Enums\TipoLectura;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una carta elegida dentro de una lectura (incluidas las aclaratorias).
 *
 * @property int $id
 * @property int $lectura_id
 * @property int $posicion
 * @property int $indice_abanico
 * @property int $carta_id
 * @property bool $invertida
 * @property bool $es_aclaratoria
 * @property int|null $aclara_posicion
 * @property string|null $nota
 * @property Carbon|null $revelada_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['posicion', 'indice_abanico', 'nota'])] // carta_id, invertida y aclaratoria los asigna solo el servidor
#[Hidden(['carta_id', 'invertida', 'carta'])] // red de seguridad: qué carta es se muestra solo a propósito, al revelar
class LecturaCarta extends Model
{
    protected $table = 'lectura_cartas';

    protected function casts(): array
    {
        return [
            'invertida' => 'boolean',
            'es_aclaratoria' => 'boolean',
            'revelada_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lectura, $this> */
    public function lectura(): BelongsTo
    {
        return $this->belongsTo(Lectura::class);
    }

    /** @return BelongsTo<Carta, $this> */
    public function carta(): BelongsTo
    {
        return $this->belongsTo(Carta::class);
    }

    /** "Pasado", o "Aclaración de «Pasado»" si es una aclaratoria. Necesita la lectura cargada. */
    public function nombrePosicion(): string
    {
        $tipo = $this->lectura->tipo;

        return $this->es_aclaratoria
            ? 'Aclaración de «'.$tipo->nombrePosicion($this->aclara_posicion).'»'
            : $tipo->nombrePosicion($this->posicion);
    }

    public function estaRevelada(): bool
    {
        return $this->revelada_at !== null;
    }

    /*
    | Las DOS únicas funciones que deciden qué se publica de una carta elegida.
    | Las usan los eventos (CartaElegida, CartaRevelada) y GET /c/{uuid}/estado,
    | así lo que viaja por Reverb y lo que se ve al recargar no pueden divergir.
    */

    /** Elegida y boca abajo: dónde está, nada de qué carta es. */
    public function datosElegida(): array
    {
        return [
            'posicion' => $this->posicion,
            'indice_abanico' => $this->indice_abanico,
            'aclara' => $this->aclara_posicion, // a qué carta aclara (null si es de la tirada): público, sirve para ubicarla
        ];
    }

    /** Revelada: recién acá va la carta, con el significado según cómo salió. */
    public function datosRevelada(): array
    {
        $carta = $this->carta;
        $tipo = $this->lectura->tipo;

        $datos = [
            'posicion' => $this->posicion,
            'nombre_posicion' => $this->nombrePosicion(),
            'invertida' => $this->invertida,
            'carta' => [
                'nombre' => $carta->nombre,
                'imagen' => $carta->urlImagen(),
                'significado' => $this->invertida ? $carta->significado_invertido : $carta->significado_derecho,
                'palabras_clave' => $carta->palabras_clave,
            ],
        ];

        // En el sí o no, la respuesta (calculada acá, con la regla de SiNo::segun) viaja
        // recién con la carta revelada: antes no puede saberse, porque sale de la carta.
        if ($tipo === TipoLectura::SiNo) {
            $respuesta = $carta->si_no->segun($this->invertida);
            $datos['respuesta'] = ['valor' => $respuesta->value, 'texto' => $respuesta->etiqueta()];
        }

        return $datos;
    }
}
