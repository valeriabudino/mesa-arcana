<?php

namespace App\Events;

use App\Models\LecturaCarta;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La tarotista dio vuelta una carta. Recién acá viaja qué carta es. */
class CartaRevelada implements ShouldBroadcastNow
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
        return 'CartaRevelada';
    }

    public function broadcastWith(): array
    {
        // lectura: a qué lectura pertenece (una consulta puede tener varias)
        return ['lectura' => $this->lecturaCarta->lectura_id] + $this->lecturaCarta->datosRevelada();
    }
}
