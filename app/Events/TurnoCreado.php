<?php

namespace App\Events;

use App\Models\Turno;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Alguien entró a la fila. Solo lo ven las tarotistas (canal privado). */
class TurnoCreado implements ShouldBroadcastNow
{
    public function __construct(private Turno $turno) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tarotistas')];
    }

    public function broadcastAs(): string
    {
        return 'TurnoCreado';
    }

    public function broadcastWith(): array
    {
        return $this->turno->datosParaTarotistas();
    }
}
