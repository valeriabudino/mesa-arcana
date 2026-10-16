<?php

namespace App\Events;

use App\Models\Oraculo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Se reveló la carta del día: la ven girar todas las personas que tengan el inicio abierto. */
class OraculoRevelado implements ShouldBroadcastNow
{
    public function __construct(private Oraculo $oraculo) {}

    public function broadcastOn(): array
    {
        return [new Channel('oraculo')]; // PÚBLICO: el inicio no tiene login
    }

    public function broadcastAs(): string
    {
        return 'OraculoRevelado';
    }

    public function broadcastWith(): array
    {
        return $this->oraculo->datosPublicos();
    }
}
