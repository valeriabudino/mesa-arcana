<?php

namespace App\Events;

use App\Models\Turno;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La consultante cambió su motivo mientras espera. Mismos datos que TurnoCreado. */
class TurnoActualizado implements ShouldBroadcastNow
{
    public function __construct(private Turno $turno) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tarotistas')];
    }

    public function broadcastAs(): string
    {
        return 'TurnoActualizado';
    }

    public function broadcastWith(): array
    {
        return $this->turno->datosParaTarotistas();
    }
}
