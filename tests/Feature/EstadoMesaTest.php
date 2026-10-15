<?php

use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\MazoMezclado;
use App\Events\SesionFinalizada;
use App\Models\Sesion;
use App\Models\User;
use Database\Seeders\CartaSeeder;
use Illuminate\Support\Facades\Event;

/*
| GET /s/{uuid}/estado: la "foto" de la mesa para dibujar al entrar y al reconectar.
| Tiene que cumplir las MISMAS reglas que los eventos: recargar la página no puede
| ser una puerta de atrás para ver cartas sin revelar.
*/

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([MazoMezclado::class, CartaElegida::class, CartaRevelada::class, SesionFinalizada::class]);

    $this->consultante = User::factory()->consultante()->create();
    $this->sesion = Sesion::factory()->conConsultante($this->consultante)->create();
    $this->tarotista = $this->sesion->tarotista;

    $this->actingAs($this->tarotista)->postJson(route('sesiones.mezclar', $this->sesion));
    foreach ([5, 40, 77] as $indice) {
        $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $this->sesion), ['indice_abanico' => $indice]);
    }
    $this->actingAs($this->tarotista)->postJson(route('sesiones.revelar', $this->sesion), ['posicion' => 1]);
});

it('las cartas sin revelar dicen dónde están, pero no qué carta son', function () {
    $respuesta = $this->actingAs($this->consultante)->getJson(route('sesiones.estado', $this->sesion))->assertOk();

    expect($respuesta->json('estado'))->toBe('revelando')
        ->and($respuesta->json('cartas.0'))->toBe(['revelada' => false, 'posicion' => 0, 'indice_abanico' => 5])  // ni una clave más
        ->and($respuesta->json('cartas.2'))->toBe(['revelada' => false, 'posicion' => 2, 'indice_abanico' => 77]);

    $json = $respuesta->getContent();
    $sinRevelar = $this->sesion->cartas()->with('carta')->whereNull('revelada_at')->get();
    foreach ($sinRevelar as $elegida) {
        expect($json)->not->toContain(json_encode($elegida->carta->nombre))
            ->and($json)->not->toContain($elegida->carta->imagen);
    }
    expect($json)->not->toContain('orden_mazo')->and($json)->not->toContain('carta_id');
});

it('la carta revelada viaja igual que en los eventos CartaElegida + CartaRevelada', function () {
    $respuesta = $this->actingAs($this->consultante)->getJson(route('sesiones.estado', $this->sesion))->assertOk();

    Event::assertDispatched(CartaRevelada::class, function (CartaRevelada $evento) use ($respuesta) {
        // indice_abanico (de CartaElegida) hace falta para redibujar la mesa al recargar
        expect($respuesta->json('cartas.1'))->toBe(['revelada' => true, 'posicion' => 1, 'indice_abanico' => 40] + $evento->broadcastWith());

        return true;
    });
});

it('al finalizar trae el link al resumen', function () {
    foreach ([0, 2] as $posicion) {
        $this->actingAs($this->tarotista)->postJson(route('sesiones.revelar', $this->sesion), ['posicion' => $posicion]);
    }
    $this->actingAs($this->tarotista)->postJson(route('sesiones.finalizar', $this->sesion))->assertNoContent();

    $this->actingAs($this->tarotista)->getJson(route('sesiones.estado', $this->sesion))
        ->assertOk()
        ->assertJsonPath('estado', 'finalizada')
        ->assertJsonPath('url_resumen', route('sesiones.resumen', $this->sesion));
});

it('solo la ven quienes participan', function () {
    $this->actingAs(User::factory()->consultante()->create())->getJson(route('sesiones.estado', $this->sesion))->assertForbidden();
    $this->actingAs(User::factory()->tarotista()->create())->getJson(route('sesiones.estado', $this->sesion))->assertForbidden();
});

it('sin login no hay estado', function () {
    auth()->logout(); // el beforeEach dejó a la tarotista logueada

    $this->getJson(route('sesiones.estado', $this->sesion))->assertUnauthorized();
});
