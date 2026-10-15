<?php

namespace App\Events;

use App\Models\LecturaCarta;
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
        private LecturaCarta $lecturaCarta,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('consulta.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'CartaElegida';
    }

    public function broadcastWith(): array
    {
        // lectura: a qué lectura pertenece (una consulta puede tener varias)
        return ['lectura' => $this->lecturaCarta->lectura_id] + $this->lecturaCarta->datosElegida();
    }
}
