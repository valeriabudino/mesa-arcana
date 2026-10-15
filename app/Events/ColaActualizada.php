<?php

namespace App\Events;

use App\Models\Turno;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * La fila cambió. Va por un canal PÚBLICO, así que lleva solo los uuids de los
 * turnos que esperan, en orden. Cada consultante busca el suyo y calcula
 * "hay N antes que vos": nadie ve nombres ajenos.
 */
class ColaActualizada implements ShouldBroadcastNow
{
    /** @var list<string> */
    private array $turnos;

    public function __construct()
    {
        // Se calcula al crear el evento (después del commit): es la fila vigente.
        $this->turnos = Turno::uuidsEnEspera();
    }

    public function broadcastOn(): array
    {
        return [new Channel('sala-espera')];
    }

    public function broadcastAs(): string
    {
        return 'ColaActualizada';
    }

    public function broadcastWith(): array
    {
        return ['turnos' => $this->turnos];
    }
}
