<?php

namespace App\Events;

use App\Models\Lectura;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * El borrador de la interpretación, mientras se escribe y al terminar. Va por el canal
 * PRIVADO de la tarotista: la consultante recién la ve cuando se publica.
 */
class BorradorInterpretacion implements ShouldBroadcastNow
{
    public function __construct(
        private Lectura $lectura,
        private string $texto,
        private bool $terminado,
        private string $fuente,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->lectura->consulta->tarotista_id)];
    }

    public function broadcastAs(): string
    {
        return 'BorradorInterpretacion';
    }

    public function broadcastWith(): array
    {
        return [
            'consulta' => $this->lectura->consulta->uuid,
            'lectura' => $this->lectura->id,
            'texto' => $this->texto, // acumulado: si se pierde un fragmento, el siguiente lo trae
            'terminado' => $this->terminado,
            'fuente' => $this->fuente,
        ];
    }
}
