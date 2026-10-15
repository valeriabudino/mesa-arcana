<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Una tarotista tomó un turno: en los demás paneles la tarjeta se va con "Lo tomó …". */
class TurnoTomado implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private string $tarotista,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tarotistas')];
    }

    public function broadcastAs(): string
    {
        return 'TurnoTomado';
    }

    public function broadcastWith(): array
    {
        return ['uuid' => $this->uuid, 'tarotista' => $this->tarotista];
    }
}
