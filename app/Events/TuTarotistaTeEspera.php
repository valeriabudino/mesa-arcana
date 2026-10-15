<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** El aviso personal: solo lo recibe la consultante atendida (su canal privado). */
class TuTarotistaTeEspera implements ShouldBroadcastNow
{
    public function __construct(
        private int $consultanteId,
        private string $tarotista,
        private string $urlConsulta,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->consultanteId)];
    }

    public function broadcastAs(): string
    {
        return 'TuTarotistaTeEspera';
    }

    public function broadcastWith(): array
    {
        return ['tarotista' => $this->tarotista, 'url' => $this->urlConsulta];
    }
}
