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
 * @property int|null $lectura_id (en las cartas reveladas y la carta astral)
 * @property int|null $pregunta_id (en el pedido de servicio: la pregunta elegida)
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

    /** @return BelongsTo<Lectura, $this> */
    public function lectura(): BelongsTo
    {
        return $this->belongsTo(Lectura::class);
    }

    /** @return BelongsTo<Pregunta, $this> */
    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class);
    }

    /**
     * Lo ÚNICO que se publica de un mensaje: lo usan el evento MensajeEnviado y
     * GET /c/{uuid}/mensajes. El contenido va tal cual se escribió (texto plano):
     * quien lo muestra lo pone con textContent / {{ }}, nunca como HTML.
     *
     * @return array<string, mixed>
     */
    public function datosPublicos(): array
    {
        return [
            'id' => $this->id, // incremental: el navegador pide "los posteriores a este" al reconectar
            'tipo' => $this->tipo->value,
            'autor' => $this->autora ? ['rol' => $this->autora->rol->value, 'nombre' => $this->autora->name] : null,
            'contenido' => $this->contenido,
            'servicio' => $this->servicio?->value,
            'estado_pedido' => $this->estado_pedido?->value,
            'pregunta' => $this->pregunta?->datosPublicos(), // la del pedido: tema y texto
            'ancla' => $this->ancla_tipo ? ['tipo' => $this->ancla_tipo->value, 'valor' => $this->ancla_valor] : null,
            'creado' => $this->created_at->toIso8601String(),
        ] + $this->datosDeLaLectura();
    }

    /**
     * Las cartas reveladas y la carta astral llevan lo mismo que sus eventos
     * (CartaRevelada, CartaAstralCalculada): ya son públicos para las dos.
     * Los datos de nacimiento NO: son personales y se piden aparte a /estado.
     *
     * @return array<string, mixed>
     */
    private function datosDeLaLectura(): array
    {
        if ($this->tipo === TipoMensaje::Carta) {
            $carta = $this->lectura->cartas->firstWhere('posicion', (int) $this->ancla_valor);

            return ['lectura' => $this->lectura_id, 'revelada' => $carta->setRelation('lectura', $this->lectura)->datosRevelada()];
        }

        if ($this->tipo === TipoMensaje::Interpretacion) {
            return ['lectura' => $this->lectura_id];
        }

        if ($this->tipo === TipoMensaje::CartaAstral) {
            return ['lectura' => $this->lectura_id, 'resultado' => $this->lectura->resultado];
        }

        return [];
    }
}
