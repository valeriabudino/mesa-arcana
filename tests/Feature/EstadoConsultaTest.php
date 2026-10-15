<?php

use App\Enums\EstadoLectura;
use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\MazoMezclado;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\CartaSeeder;
use Illuminate\Support\Facades\Event;

/*
| GET /c/{uuid}/estado: la "foto" de la consulta para dibujar al entrar y al reconectar.
| Tiene que cumplir las MISMAS reglas que los eventos: recargar la página no puede
| ser una puerta de atrás para ver cartas sin revelar.
*/

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([MazoMezclado::class, CartaElegida::class, CartaRevelada::class]);

    $this->consulta = Consulta::factory()->create();
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
    $this->lectura = Lectura::factory()->for($this->consulta)->create(['pregunta' => '¿Me mudo?']);

    $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $this->lectura));
    foreach ([5, 40, 77] as $indice) {
        $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => $indice]);
    }
    $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 1]);
});

it('trae la lectura activa con su tipo, estado y pregunta', function () {
    $this->actingAs($this->consultante)->getJson(route('consultas.estado', $this->consulta))
        ->assertOk()
        ->assertJsonPath('estado', 'activa')
        ->assertJsonPath('lectura.id', $this->lectura->id)
        ->assertJsonPath('lectura.tipo', 'tres_cartas')
        ->assertJsonPath('lectura.estado', 'revelando')
        ->assertJsonPath('lectura.pregunta', '¿Me mudo?');
});

it('las cartas sin revelar dicen dónde están, pero no qué carta son', function () {
    $respuesta = $this->actingAs($this->consultante)->getJson(route('consultas.estado', $this->consulta))->assertOk();

    expect($respuesta->json('lectura.cartas.0'))->toBe(['revelada' => false, 'posicion' => 0, 'indice_abanico' => 5])  // ni una clave más
        ->and($respuesta->json('lectura.cartas.2'))->toBe(['revelada' => false, 'posicion' => 2, 'indice_abanico' => 77]);

    $json = $respuesta->getContent();
    foreach ($this->lectura->cartas()->with('carta')->whereNull('revelada_at')->get() as $elegida) {
        expect($json)->not->toContain(json_encode($elegida->carta->nombre))
            ->and($json)->not->toContain($elegida->carta->imagen);
    }
    expect($json)->not->toContain('orden_mazo')->and($json)->not->toContain('carta_id');
});

it('la carta revelada viaja igual que en los eventos CartaElegida + CartaRevelada', function () {
    $respuesta = $this->actingAs($this->consultante)->getJson(route('consultas.estado', $this->consulta))->assertOk();

    Event::assertDispatched(CartaRevelada::class, function (CartaRevelada $evento) use ($respuesta) {
        // indice_abanico (de CartaElegida) hace falta para redibujar la mesa al recargar
        expect($respuesta->json('lectura.cartas.1'))->toBe(['revelada' => true, 'posicion' => 1, 'indice_abanico' => 40] + $evento->broadcastWith());

        return true;
    });
});

it('sin lectura activa, lectura es null', function () {
    $this->lectura->forceFill(['estado' => EstadoLectura::Cerrada])->save();

    $this->actingAs($this->tarotista)->getJson(route('consultas.estado', $this->consulta))
        ->assertOk()
        ->assertJsonPath('lectura', null);
});

it('solo la ven quienes participan', function () {
    $this->actingAs(User::factory()->consultante()->create())->getJson(route('consultas.estado', $this->consulta))->assertForbidden();
    $this->actingAs(User::factory()->tarotista()->create())->getJson(route('consultas.estado', $this->consulta))->assertForbidden();
    $this->actingAs(User::factory()->tarotista()->create())->get(route('consultas.show', $this->consulta))->assertForbidden();
});

it('sin login no hay estado', function () {
    auth()->logout(); // el beforeEach dejó a la tarotista logueada

    $this->getJson(route('consultas.estado', $this->consulta))->assertUnauthorized();
});
