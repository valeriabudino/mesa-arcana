<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** El saldo de una persona cambió (plan v3 §13.7). Va por su canal PRIVADO: nadie más lo ve. */
class SaldoActualizado implements ShouldBroadcastNow
{
    public function __construct(
        private int $userId,
        private int $disponible,
        private int $reservado,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'SaldoActualizado';
    }

    public function broadcastWith(): array
    {
        return ['disponible' => $this->disponible, 'reservado' => $this->reservado];
    }
}
