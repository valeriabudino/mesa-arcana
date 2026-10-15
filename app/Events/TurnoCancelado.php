<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La consultante salió de la fila. */
class TurnoCancelado implements ShouldBroadcastNow
{
    public function __construct(private string $uuid) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tarotistas')];
    }

    public function broadcastAs(): string
    {
        return 'TurnoCancelado';
    }

    public function broadcastWith(): array
    {
        return ['uuid' => $this->uuid];
    }
}
