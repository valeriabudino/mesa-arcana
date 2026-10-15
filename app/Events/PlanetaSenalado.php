<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La tarotista señala un punto de la rueda: foco y cámara en las dos pantallas. */
class PlanetaSenalado implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private int $lecturaId,
        private string $planeta,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'PlanetaSenalado';
    }

    public function broadcastWith(): array
    {
        return ['lectura' => $this->lecturaId, 'planeta' => $this->planeta];
    }
}
