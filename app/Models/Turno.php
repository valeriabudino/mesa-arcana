<?php

namespace App\Models;

use App\Enums\EstadoTurno;
use App\Models\Concerns\TieneMaquinaDeEstados;
use App\Models\Concerns\TieneUuidPublico;
use Database\Factories\TurnoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Un lugar en la fila de la sala de espera.
 *
 * @property int $id
 * @property string $uuid
 * @property int $consultante_id
 * @property string|null $motivo
 * @property EstadoTurno $estado
 * @property int|null $tarotista_id
 * @property Carbon|null $tomado_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['motivo'])] // estado y tarotista los maneja solo ColaService
class Turno extends Model
{
    /** @use HasFactory<TurnoFactory> */
    use HasFactory, TieneMaquinaDeEstados, TieneUuidPublico;

    protected $attributes = [
        'estado' => 'esperando',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoTurno::class,
            'tomado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function consultante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultante_id');
    }

    /** @return BelongsTo<User, $this> */
    public function tarotista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tarotista_id');
    }

    /** @return HasOne<Consulta, $this> */
    public function consulta(): HasOne
    {
        return $this->hasOne(Consulta::class);
    }
}
