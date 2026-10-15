<?php

namespace App\Events;

use App\Models\Mensaje;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Un mensaje nuevo en el chat (de una de las dos, de sistema o un pedido de servicio). */
class MensajeEnviado implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private Mensaje $mensaje,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'MensajeEnviado';
    }

    public function broadcastWith(): array
    {
        return $this->mensaje->datosPublicos();
    }
}
