<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Una lectura cambió de estado por una decisión de la tarotista (por ejemplo, se cerró). */
class EstadoCambiado implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private int $lecturaId,
        private string $estado,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'EstadoCambiado';
    }

    public function broadcastWith(): array
    {
        return ['lectura' => $this->lecturaId, 'estado' => $this->estado];
    }
}
