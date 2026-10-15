<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Se aceptó una carta aclaratoria: el abanico se vuelve a abrir para elegir una más. */
class AclaratoriaAceptada implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private int $lecturaId,
        private int $aclara,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'AclaratoriaAceptada';
    }

    public function broadcastWith(): array
    {
        return ['lectura' => $this->lecturaId, 'aclara' => $this->aclara];
    }
}
