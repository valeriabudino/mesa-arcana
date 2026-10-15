<?php

use App\Enums\EstadoConsulta;
use App\Enums\EstadoLectura;
use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\MazoMezclado;
use App\Models\Carta;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\CartaSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([MazoMezclado::class, CartaElegida::class, CartaRevelada::class]);

    $this->consulta = Consulta::factory()->create();
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
    $this->lectura = Lectura::factory()->for($this->consulta)->create(); // tres cartas, Lista
});

/** Mezcla y elige las tres cartas: deja la lectura en Revelando. */
function elegirLasTres(): void
{
    test()->actingAs(test()->tarotista)->postJson(rutaLectura('mezclar', test()->lectura));
    foreach ([1, 2, 3] as $indice) {
        test()->actingAs(test()->consultante)->postJson(rutaLectura('elegir', test()->lectura), ['indice_abanico' => $indice]);
    }
}

it('recorre la tirada completa de punta a punta', function () {
    $lectura = $this->lectura;

    // Mezclar: orden_mazo con las 78 cartas, sin repetir
    $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $lectura))->assertNoContent();
    $orden = $lectura->fresh()->orden_mazo;
    expect($lectura->fresh()->estado)->toBe(EstadoLectura::Eligiendo)
        ->and($orden)->toHaveCount(78)
        ->and(array_unique($orden))->toHaveCount(78)
        ->and(collect($orden)->sort()->values()->all())->toBe(Carta::orderBy('id')->pluck('id')->all());
    Event::assertDispatched(MazoMezclado::class);

    // Elegir 3: la última pasa sola a Revelando
    foreach ([10, 40, 70] as $i => $indice) {
        $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $lectura), ['indice_abanico' => $indice])->assertNoContent();
        expect($lectura->fresh()->cartas[$i]->carta_id)->toBe($orden[$indice]); // la carta sale del orden del servidor
    }
    expect($lectura->fresh()->estado)->toBe(EstadoLectura::Revelando);
    Event::assertDispatchedTimes(CartaElegida::class, 3);

    // Revelar las 3 (en cualquier orden): con la última, la lectura queda Abierta
    foreach ([2, 0, 1] as $posicion) {
        $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $lectura), ['posicion' => $posicion])->assertNoContent();
    }
    expect($lectura->fresh()->estado)->toBe(EstadoLectura::Abierta);
    Event::assertDispatchedTimes(CartaRevelada::class, 3);
});

describe('403: no sos vos', function () {
    it('la consultante no puede mezclar ni revelar', function () {
        $this->actingAs($this->consultante)->postJson(rutaLectura('mezclar', $this->lectura))->assertForbidden();
        $this->actingAs($this->consultante)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 0])->assertForbidden();
    });

    it('la tarotista no puede elegir', function () {
        $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $this->lectura));
        $this->actingAs($this->tarotista)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 3])->assertForbidden();
    });

    it('nadie de afuera de la consulta toca la lectura', function (string $rol) {
        $this->actingAs(User::factory()->{$rol}()->create())->postJson(rutaLectura('mezclar', $this->lectura))->assertForbidden();
    })->with(['tarotista', 'consultante']);

    it('sin login, las acciones redirigen al login', function (string $accion) {
        $this->post(rutaLectura($accion, $this->lectura))->assertRedirect(route('login'));
    })->with(['mezclar', 'elegir', 'revelar']);
});

it('una lectura de otra consulta no se alcanza por la URL de esta (404)', function () {
    $ajena = Lectura::factory()->create(); // otra consulta, otras personas

    // Aunque sea la tarotista de ESTA consulta, la URL mezcla consultas: scopeBindings corta antes de la policy.
    $this->actingAs($this->tarotista)
        ->postJson(route('consultas.lecturas.mezclar', [$this->consulta, $ajena]))
        ->assertNotFound();
});

describe('409: no es el momento', function () {
    it('no se puede mezclar dos veces', function () {
        $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $this->lectura))->assertNoContent();
        $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $this->lectura))->assertConflict();
    });

    it('no se puede elegir antes de mezclar', function () {
        $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 3])->assertConflict();
    });

    it('doble clic sobre la misma carta: la segunda da 409 y no se duplica', function () {
        $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $this->lectura));

        $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 7])->assertNoContent();
        $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 7])->assertConflict();

        expect($this->lectura->cartas()->count())->toBe(1);
    });

    it('no se puede elegir más cartas que las de la tirada', function () {
        elegirLasTres();

        $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 4])->assertConflict();
        expect($this->lectura->cartas()->count())->toBe(3);
    });

    it('no se revela dos veces', function () {
        elegirLasTres();

        $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 0])->assertNoContent();
        $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 0])->assertConflict();
    });

    it('con la consulta finalizada no se mueve nada', function () {
        $this->consulta->forceFill(['estado' => EstadoConsulta::Finalizada])->save();

        $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $this->lectura))->assertConflict();
        expect($this->lectura->fresh()->estado)->toBe(EstadoLectura::Lista);
    });
});

it('valida el índice del abanico y la posición', function () {
    $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $this->lectura));

    $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 78])->assertUnprocessable();
    $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => -1])->assertUnprocessable();
    $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 3])->assertUnprocessable();
});
