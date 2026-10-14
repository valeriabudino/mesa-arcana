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
}
