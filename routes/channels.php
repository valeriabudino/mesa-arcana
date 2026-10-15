<?php

use App\Models\Consulta;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
| Canal de PRESENCIA de la sala privada (consulta).
| Solo autoriza (no modifica nada): se ejecuta en cada (re)conexión de Echo.
| Lo que devuelve lo ven todos los del canal: solo id, nombre y rol (nada de email).
*/
Broadcast::channel('consulta.{uuid}', function (User $user, string $uuid) {
    $consulta = Consulta::where('uuid', $uuid)->first();

    if (! $consulta || ! $consulta->participa($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'rol' => $user->rol->value,
    ];
});
