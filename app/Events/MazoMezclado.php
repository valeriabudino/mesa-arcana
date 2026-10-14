<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Se mezcló el mazo (Lista → Eligiendo).
 * La semilla es SOLO visual: decide el dibujo de la animación, no tiene relación con orden_mazo.
 */
class MazoMezclado implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private int $semillaAnimacion,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('sesion.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'MazoMezclado';
    }

    public function broadcastWith(): array
    {
        return ['semilla_animacion' => $this->semillaAnimacion];
    }
}
