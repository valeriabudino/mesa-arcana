<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La tarotista aceptó o rechazó un pedido: la tarjeta del chat cambia de estado. */
class PedidoResuelto implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private int $mensajeId,
        private string $estado,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'PedidoResuelto';
    }

    public function broadcastWith(): array
    {
        return ['mensaje' => $this->mensajeId, 'estado_pedido' => $this->estado];
    }
}
