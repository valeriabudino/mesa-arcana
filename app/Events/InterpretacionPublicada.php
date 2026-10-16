<?php

namespace App\Events;

use App\Models\Mensaje;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La tarotista publicó la interpretación: entra al chat de las dos (es un mensaje más). */
class InterpretacionPublicada implements ShouldBroadcastNow
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
        return 'InterpretacionPublicada';
    }

    public function broadcastWith(): array
    {
        return $this->mensaje->datosPublicos();
    }
}
