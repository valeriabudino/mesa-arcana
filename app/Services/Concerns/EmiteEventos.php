<?php

namespace App\Services\Concerns;

use Illuminate\Broadcasting\BroadcastException;

/**
 * Emite un evento por Reverb DESPUÉS del commit. Si Reverb no responde, la acción
 * igual ocurrió (ya está guardada): se deja registro en el log y no se le informa
 * como fallida a quien la hizo. Las pantallas se ponen al día solas al reconectarse
 * (here() → GET /estado).
 */
trait EmiteEventos
{
    protected function emitir(object $evento): void
    {
        try {
            broadcast($evento);
        } catch (BroadcastException $e) {
            report($e);
        }
    }
}
