<?php

use App\Events\ColaActualizada;
use App\Events\TurnoCreado;
use App\Events\TuTarotistaTeEspera;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;

/*
| Qué viaja por cada canal de la sala de espera. El punto de privacidad del plan:
| por el canal PÚBLICO solo van uuids, nunca nombres ni motivos.
*/

it('ColaActualizada va por el canal público y lleva SOLO los uuids en orden de llegada', function () {
    $maria = User::factory()->invitada()->create(['name' => 'María Secreta']);
    $primero = Turno::factory()->create(['consultante_id' => $maria->id, 'motivo' => 'Motivo privado', 'created_at' => now()->subMinutes(5)]);
    $segundo = Turno::factory()->create(['created_at' => now()->subMinute()]);
    Turno::factory()->tomado()->create(); // los atendidos no están en la fila

    $evento = new ColaActualizada;
    $json = json_encode($evento->broadcastWith(), JSON_UNESCAPED_UNICODE);

    expect($evento->broadcastOn()[0])->toBeInstanceOf(Channel::class)
        ->and($evento->broadcastOn()[0])->not->toBeInstanceOf(PrivateChannel::class)
        ->and($evento->broadcastOn()[0]->name)->toBe('sala-espera')
        ->and($evento->broadcastWith())->toBe(['turnos' => [$primero->uuid, $segundo->uuid]]) // ni una clave más
        ->and($json)->not->toContain('María')
        ->and($json)->not->toContain('Motivo privado');
});

it('TurnoCreado va solo a las tarotistas, con lo que muestra la tarjeta y nada más', function () {
    $turno = Turno::factory()->create(['motivo' => 'Trabajo nuevo']);
    $evento = new TurnoCreado($turno);

    expect($evento->broadcastOn()[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($evento->broadcastOn()[0]->name)->toBe('private-tarotistas')
        ->and(array_keys($evento->broadcastWith()))->toBe(['uuid', 'nombre', 'motivo', 'desde'])
        ->and($evento->broadcastWith()['motivo'])->toBe('Trabajo nuevo');
});

it('TuTarotistaTeEspera va solo al canal privado de esa consultante', function () {
    $evento = new TuTarotistaTeEspera(42, 'Valeria', 'http://localhost/c/x');

    expect($evento->broadcastOn()[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($evento->broadcastOn()[0])->not->toBeInstanceOf(PresenceChannel::class)
        ->and($evento->broadcastOn()[0]->name)->toBe('private-App.Models.User.42')
        ->and($evento->broadcastWith())->toBe(['tarotista' => 'Valeria', 'url' => 'http://localhost/c/x']);
});

it('el panel recibe la fila con exactamente los mismos datos que el evento', function () {
    $turno = Turno::factory()->create();
    $tarotista = User::factory()->tarotista()->create();

    $this->actingAs($tarotista)->getJson(route('atencion.cola'))
        ->assertOk()
        ->assertExactJson([(new TurnoCreado($turno->load('consultante')))->broadcastWith()]);
});
