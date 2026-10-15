<?php

namespace App\Policies;

use App\Models\Lectura;
use App\Models\User;

/**
 * Quién hace qué en una tirada: la tarotista mezcla y revela; la consultante elige.
 * (Que la lectura pertenezca a la consulta de la URL lo garantiza scopeBindings en las rutas.)
 */
class LecturaPolicy
{
    public function mezclar(User $user, Lectura $lectura): bool
    {
        return $lectura->consulta->esTarotista($user);
    }

    public function elegir(User $user, Lectura $lectura): bool
    {
        return $lectura->consulta->esConsultante($user);
    }

    public function revelar(User $user, Lectura $lectura): bool
    {
        return $lectura->consulta->esTarotista($user);
    }

    public function pedirAclaratoria(User $user, Lectura $lectura): bool
    {
        return $lectura->consulta->esConsultante($user);
    }

    public function cerrar(User $user, Lectura $lectura): bool
    {
        return $lectura->consulta->esTarotista($user);
    }
}
