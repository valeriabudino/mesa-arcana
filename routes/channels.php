<?php

use App\Models\Sesion;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
| Canal de PRESENCIA de la sala de lectura.
| Solo autoriza (no modifica nada): se ejecuta en cada (re)conexión de Echo.
| Lo que devuelve lo ven todos los del canal: solo id, nombre y rol (nada de email).
*/
Broadcast::channel('sesion.{uuid}', function (User $user, string $uuid) {
    $sesion = Sesion::where('uuid', $uuid)->first();

    if (! $sesion || ! $sesion->participa($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'rol' => $user->rol->value,
    ];
});
