<?php

namespace App\Models;

use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Una línea del historial de una billetera. Solo la crea BilleteraService, en la misma
 * transacción que cambia el saldo; nunca se edita ni se borra.
 *
 * @property int $id
 * @property int $user_id
 * @property TipoMovimiento $tipo
 * @property int $monto (cambio en lo disponible)
 * @property int $monto_reservado (cambio en lo reservado)
 * @property int $saldo_disponible (cómo quedó)
 * @property int $saldo_reservado
 * @property string|null $referencia_type
 * @property int|null $referencia_id
 * @property Carbon $created_at
 */
class Movimiento extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }
}
