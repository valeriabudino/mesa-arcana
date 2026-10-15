<?php

namespace App\Events;

use App\Models\Lectura;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Empieza una lectura: la mesa cambia de modo en las dos pantallas. */
class LecturaIniciada implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private Lectura $lectura,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'LecturaIniciada';
    }

    public function broadcastWith(): array
    {
        return ['lectura' => $this->lectura->datosPublicos()];
    }
}
