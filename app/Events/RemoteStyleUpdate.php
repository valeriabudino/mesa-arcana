<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Evento de prueba de la Fase 0 (emisor → receptor de la guía).
 * ShouldBroadcastNow: se envía a Reverb en el momento, sin pasar por la cola.
 */
class RemoteStyleUpdate implements ShouldBroadcastNow
{
    public function __construct(
        public string $newText,
        public string $newStyle,
    ) {}

    // Canal PÚBLICO (no requiere auth)
    public function broadcastOn(): array
    {
        return [new Channel('style-channel')];
    }

    // Nombre del evento en el frontend: se escucha con '.RemoteStyleUpdate'
    public function broadcastAs(): string
    {
        return 'RemoteStyleUpdate';
    }
}
