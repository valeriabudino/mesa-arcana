<?php

use App\Models\Consulta;
use App\Models\Turno;
use App\Models\User;

it('el inicio lleva a pedir consulta y al login de tarotistas', function () {
    $this->get('/')->assertOk()->assertSee(route('espera'))->assertSee(route('login'));
});

it('sin login, /espera muestra el formulario con el nombre', function () {
    $this->get(route('espera'))->assertOk()->assertSee('¿Cómo te llamás?');
});

it('con turno, /espera es la sala de espera', function () {
    $turno = Turno::factory()->create();

    $this->actingAs($turno->consultante)->get(route('espera'))->assertOk()->assertSee('Sala de espera')->assertSee('Salir de la fila');
});

it('si ya la atendieron, /espera la manda a la consulta', function () {
    $consulta = Consulta::factory()->create();

    $this->actingAs($consulta->consultante)->get(route('espera'))->assertRedirect(route('consultas.show', $consulta));
});

it('después del login cada rol va a su lugar', function () {
    $this->actingAs(User::factory()->tarotista()->create())->get(route('dashboard'))->assertRedirect(route('atencion'));
    $this->actingAs(User::factory()->consultante()->create())->get(route('dashboard'))->assertRedirect(route('espera'));
});

it('el panel de atención es solo para tarotistas', function () {
    $this->actingAs(User::factory()->tarotista()->create())->get(route('atencion'))->assertOk();
    $this->actingAs(User::factory()->invitada()->create())->get(route('atencion'))->assertForbidden();
    $this->actingAs(User::factory()->invitada()->create())->getJson(route('atencion.cola'))->assertForbidden();
});

it('la foto de la espera dice la fila anónima y el estado del turno propio', function () {
    $otro = Turno::factory()->create(['created_at' => now()->subMinute()]);
    $mio = Turno::factory()->create();

    $this->actingAs($mio->consultante)->getJson(route('espera.estado'))
        ->assertOk()
        ->assertExactJson([
            'turno' => ['uuid' => $mio->uuid, 'estado' => 'esperando'],
            'cola' => [$otro->uuid, $mio->uuid],
            'url_consulta' => null,
        ]);
});
