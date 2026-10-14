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

    /**
     * Ver /s/{uuid}: participantes, o cualquiera (con o sin login) si la sesión
     * todavía no tiene consultante. ?User: Laravel también la llama para visitantes.
     */
    public function entrar(?User $user, Sesion $sesion): bool
    {
        if ($user === null) {
            return $sesion->consultante_id === null;
        }

        if ($sesion->participa($user)) {
            return true;
        }

        return $user->esConsultante() && $sesion->consultante_id === null;
    }

    /** Entrar solo con el nombre: visitantes sin login y sesión sin consultante. */
    public function ingresar(?User $user, Sesion $sesion): bool
    {
        return $user === null && $sesion->consultante_id === null;
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
