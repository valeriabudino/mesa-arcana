<?php

use App\Models\Consulta;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Canal PRIVADO de cada persona: avisos personales ("tu tarotista te está esperando").
| Solo lo escucha su dueña.
*/
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
| Canal PRIVADO de las tarotistas: la fila con nombres y motivos.
| Cualquier tarotista, esté disponible o no: la autorización se evalúa una sola vez
| al suscribirse, y "Disponible" se exige donde importa, al tomar un turno.
*/
Broadcast::channel('tarotistas', fn (User $user) => $user->esTarotista());

/*
| El canal PÚBLICO sala-espera no se declara acá: los públicos no se autorizan.
| Por eso ColaActualizada lleva solo uuids anónimos.
*/

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
