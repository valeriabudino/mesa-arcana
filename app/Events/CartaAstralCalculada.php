<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** El servidor calculó la tríada: las dos pantallas arman la misma rueda con el mismo resultado. */
class CartaAstralCalculada implements ShouldBroadcastNow
{
    /** @param  array<string, mixed>  $resultado */
    public function __construct(
        private string $uuid,
        private int $lecturaId,
        private array $resultado,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'CartaAstralCalculada';
    }

    public function broadcastWith(): array
    {
        return ['lectura' => $this->lecturaId, 'resultado' => $this->resultado];
    }
}
