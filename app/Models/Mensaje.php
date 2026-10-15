<?php

namespace App\Models;

use App\Enums\EstadoPedido;
use App\Enums\TipoAncla;
use App\Enums\TipoLectura;
use App\Enums\TipoMensaje;
use Database\Factories\MensajeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un mensaje del chat de la consulta. Texto plano: se muestra con textContent.
 *
 * @property int $id
 * @property int $consulta_id
 * @property int|null $user_id (null = mensaje de sistema)
 * @property TipoMensaje $tipo
 * @property string $contenido
 * @property TipoLectura|null $servicio
 * @property TipoAncla|null $ancla_tipo
 * @property string|null $ancla_valor
 * @property EstadoPedido|null $estado_pedido
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['contenido'])] // el resto lo decide ChatService
class Mensaje extends Model
{
    /** @use HasFactory<MensajeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoMensaje::class,
            'servicio' => TipoLectura::class,
            'ancla_tipo' => TipoAncla::class,
            'estado_pedido' => EstadoPedido::class,
        ];
    }

    /** @return BelongsTo<Consulta, $this> */
    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autora(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
