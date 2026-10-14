<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La tarotista cerró la lectura (Revelando → Finalizada). */
class SesionFinalizada implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private string $urlResumen,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('sesion.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'SesionFinalizada';
    }

    public function broadcastWith(): array
    {
        return ['url_resumen' => $this->urlResumen];
    }
}
