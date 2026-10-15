<?php

namespace App\Policies;

use App\Models\Turno;
use App\Models\User;

/**
 * Los uuids de turno viajan por un canal PÚBLICO: conocer uno no autoriza nada.
 * Cada acción exige ser quien corresponde.
 */
class TurnoPolicy
{
    /** Ver la fila completa (panel de atención): solo tarotistas. */
    public function viewAny(User $user): bool
    {
        return $user->esTarotista();
    }

    /** Salir de la fila: solo la dueña del turno. */
    public function cancelar(User $user, Turno $turno): bool
    {
        return $turno->esDe($user);
    }

    /** Atender: cualquier tarotista ("Disponible" lo verifica ColaService, con lock). */
    public function tomar(User $user, Turno $turno): bool
    {
        return $user->esTarotista();
    }
}
