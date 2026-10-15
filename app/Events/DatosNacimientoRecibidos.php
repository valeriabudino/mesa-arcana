<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * La consultante cargó sus datos de nacimiento. NO viajan acá: son datos personales
 * y no hacen falta para dibujar nada (quedan en la base, para quienes participan).
 */
class DatosNacimientoRecibidos implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private int $lecturaId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'DatosNacimientoRecibidos';
    }

    public function broadcastWith(): array
    {
        return ['lectura' => $this->lecturaId];
    }
}
