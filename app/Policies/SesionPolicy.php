<?php

namespace App\Policies;

use App\Models\Sesion;
use App\Models\User;

/**
 * Responde QUIÉN puede hacer cada acción (403 si no).
 * CUÁNDO se puede lo decide la máquina de estados (409 si no).
 */
class SesionPolicy
{
    public function create(User $user): bool
    {
        return $user->esTarotista();
    }

    /** Entrar a la mesa: participantes, o la primera consultante que abre el link. */
    public function entrar(User $user, Sesion $sesion): bool
    {
        if ($sesion->participa($user)) {
            return true;
        }

        return $user->esConsultante() && $sesion->consultante_id === null;
    }

    public function mezclar(User $user, Sesion $sesion): bool
    {
        return $sesion->esTarotista($user);
    }

    public function elegir(User $user, Sesion $sesion): bool
    {
        return $sesion->esConsultante($user);
    }

    public function revelar(User $user, Sesion $sesion): bool
    {
        return $sesion->esTarotista($user);
    }

    public function finalizar(User $user, Sesion $sesion): bool
    {
        return $sesion->esTarotista($user);
    }

    public function verResumen(User $user, Sesion $sesion): bool
    {
        return $sesion->participa($user);
    }
}
