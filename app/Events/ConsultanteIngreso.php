<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La consultante quedó asociada a la sesión (Esperando → Lista). */
class ConsultanteIngreso implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private string $nombre,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('sesion.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'ConsultanteIngreso';
    }

    public function broadcastWith(): array
    {
        return ['nombre' => $this->nombre];
    }
}
