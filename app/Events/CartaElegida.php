<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * La consultante eligió una carta del abanico.
 * Viaja SIN datos de la carta: solo de dónde sale (indice_abanico) y a dónde va (posicion).
 */
class CartaElegida implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private int $posicion,
        private int $indiceAbanico,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('sesion.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'CartaElegida';
    }

    public function broadcastWith(): array
    {
        return [
            'posicion' => $this->posicion,
            'indice_abanico' => $this->indiceAbanico,
        ];
    }
}
