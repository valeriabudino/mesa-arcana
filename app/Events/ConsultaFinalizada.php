<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La consulta terminó: las dos pantallas muestran el cierre y el link al resumen. */
class ConsultaFinalizada implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private string $urlResumen,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'ConsultaFinalizada';
    }

    public function broadcastWith(): array
    {
        return ['url_resumen' => $this->urlResumen];
    }
}
