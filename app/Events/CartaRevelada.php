<?php

namespace App\Events;

use App\Models\SesionCarta;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La tarotista dio vuelta una carta. Recién acá viaja qué carta es. */
class CartaRevelada implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private SesionCarta $sesionCarta,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('sesion.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'CartaRevelada';
    }

    public function broadcastWith(): array
    {
        return $this->sesionCarta->datosRevelada();
    }
}
