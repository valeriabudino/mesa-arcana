<?php

namespace App\Policies;

use App\Models\Consulta;
use App\Models\User;

/**
 * Responde QUIÉN puede hacer cada acción en la sala privada (403 si no).
 * CUÁNDO se puede lo deciden las máquinas de estado (409 si no).
 */
class ConsultaPolicy
{
    /** Entrar a la sala y pedir su estado: solo las dos que participan. */
    public function ver(User $user, Consulta $consulta): bool
    {
        return $consulta->participa($user);
    }
}
