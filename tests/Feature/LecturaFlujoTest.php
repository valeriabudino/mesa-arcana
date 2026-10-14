<?php

use App\Enums\EstadoSesion;
use App\Enums\TipoTirada;
use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\ConsultanteIngreso;
use App\Events\MazoMezclado;
use App\Events\SesionFinalizada;
use App\Models\Carta;
use App\Models\Sesion;
use App\Models\User;
use Database\Seeders\CartaSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([ConsultanteIngreso::class, MazoMezclado::class, CartaElegida::class, CartaRevelada::class, SesionFinalizada::class]);

    $this->tarotista = User::factory()->tarotista()->create();
    $this->consultante = User::factory()->consultante()->create();
});

/** Sesión de 3 cartas con la consultante ya adentro. */
function sesionLista(): Sesion
{
    $sesion = Sesion::factory()->create(['tarotista_id' => test()->tarotista->id, 'tipo_tirada' => TipoTirada::TresCartas]);
    test()->actingAs(test()->consultante)->get(route('sesiones.show', $sesion))->assertOk();

    return $sesion->fresh();
}

it('recorre la lectura completa de punta a punta', function () {
    // Crear
    $this->actingAs($this->tarotista)
        ->post(route('sesiones.store'), ['tipo_tirada' => 'tres_cartas', 'pregunta' => '¿Cambio de trabajo?'])
        ->assertRedirect();
    $sesion = Sesion::sole();
    expect($sesion->estado)->toBe(EstadoSesion::Esperando);

    // La consultante abre el link sin cuenta y entra solo con su nombre
    auth()->logout();
    $this->get(route('sesiones.show', $sesion))->assertOk()->assertSee('¿Cómo te llamás?');
    $this->post(route('sesiones.ingresar', $sesion), ['nombre' => 'María'])->assertRedirect(route('sesiones.show', $sesion));
    $this->consultante = User::firstWhere('name', 'María');
    expect($sesion->fresh()->estado)->toBe(EstadoSesion::Lista)
        ->and($sesion->fresh()->consultante_id)->toBe($this->consultante->id);
    Event::assertDispatched(ConsultanteIngreso::class);

    // Mezclar: orden_mazo con las 78 cartas, sin repetir
    $this->actingAs($this->tarotista)->postJson(route('sesiones.mezclar', $sesion))->assertNoContent();
    $orden = $sesion->fresh()->orden_mazo;
    expect($sesion->fresh()->estado)->toBe(EstadoSesion::Eligiendo)
        ->and($orden)->toHaveCount(78)
        ->and(array_unique($orden))->toHaveCount(78)
        ->and(collect($orden)->sort()->values()->all())->toBe(Carta::orderBy('id')->pluck('id')->all());
    Event::assertDispatched(MazoMezclado::class);

    // Elegir 3: la última pasa sola a Revelando
    foreach ([10, 40, 70] as $i => $indice) {
        $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => $indice])->assertNoContent();
        expect($sesion->fresh()->cartas[$i]->carta_id)->toBe($orden[$indice]); // la carta sale del orden del servidor
    }
    expect($sesion->fresh()->estado)->toBe(EstadoSesion::Revelando);
    Event::assertDispatchedTimes(CartaElegida::class, 3);

    // Revelar las 3 y finalizar
    foreach ([0, 1, 2] as $posicion) {
        $this->actingAs($this->tarotista)->postJson(route('sesiones.revelar', $sesion), ['posicion' => $posicion])->assertNoContent();
    }
    Event::assertDispatchedTimes(CartaRevelada::class, 3);

    $this->actingAs($this->tarotista)->postJson(route('sesiones.finalizar', $sesion))->assertNoContent();
    expect($sesion->fresh()->estado)->toBe(EstadoSesion::Finalizada)
        ->and($sesion->fresh()->finalizada_at)->not->toBeNull();
    Event::assertDispatched(SesionFinalizada::class);

    // Resumen: las 3 cartas con su nombre
    $resumen = $this->actingAs($this->consultante)->get(route('sesiones.resumen', $sesion))->assertOk();
    $sesion->fresh()->cartas->each(fn ($sc) => $resumen->assertSee($sc->carta->nombre));
});

describe('403: no sos vos', function () {
    it('la consultante no puede mezclar', function () {
        $sesion = sesionLista();
        $this->actingAs($this->consultante)->postJson(route('sesiones.mezclar', $sesion))->assertForbidden();
    });

    it('otra tarotista no puede mezclar una sesión ajena', function () {
        $sesion = sesionLista();
        $this->actingAs(User::factory()->tarotista()->create())->postJson(route('sesiones.mezclar', $sesion))->assertForbidden();
    });

    it('la tarotista no puede elegir', function () {
        $sesion = sesionLista();
        $this->actingAs($this->tarotista)->postJson(route('sesiones.mezclar', $sesion));
        $this->actingAs($this->tarotista)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => 3])->assertForbidden();
    });

    it('una segunda consultante no entra a una sesión ocupada', function () {
        $sesion = sesionLista();
        $this->actingAs(User::factory()->consultante()->create())->get(route('sesiones.show', $sesion))->assertForbidden();
    });

    it('una consultante no puede crear sesiones', function () {
        $this->actingAs($this->consultante)->post(route('sesiones.store'), ['tipo_tirada' => 'si_no'])->assertForbidden();
    });

    it('sin login, las acciones de la mesa redirigen al login', function (string $ruta) {
        $sesion = Sesion::factory()->create();
        $this->post(route($ruta, $sesion))->assertRedirect(route('login'));
    })->with(['sesiones.mezclar', 'sesiones.elegir', 'sesiones.revelar', 'sesiones.finalizar']);
});

describe('409: no es el momento', function () {
    it('no se puede mezclar dos veces', function () {
        $sesion = sesionLista();
        $this->actingAs($this->tarotista)->postJson(route('sesiones.mezclar', $sesion))->assertNoContent();
        $this->actingAs($this->tarotista)->postJson(route('sesiones.mezclar', $sesion))->assertConflict();
    });

    it('no se puede elegir antes de mezclar', function () {
        $sesion = sesionLista();
        $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => 3])->assertConflict();
    });

    it('doble clic sobre la misma carta: la segunda da 409 y no se duplica', function () {
        $sesion = sesionLista();
        $this->actingAs($this->tarotista)->postJson(route('sesiones.mezclar', $sesion));

        $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => 7])->assertNoContent();
        $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => 7])->assertConflict();

        expect($sesion->cartas()->count())->toBe(1);
    });

    it('no se puede elegir más cartas que las de la tirada', function () {
        $sesion = sesionLista();
        $this->actingAs($this->tarotista)->postJson(route('sesiones.mezclar', $sesion));
        foreach ([1, 2, 3] as $indice) {
            $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => $indice]);
        }

        $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => 4])->assertConflict();
        expect($sesion->cartas()->count())->toBe(3);
    });

    it('no se revela dos veces ni se finaliza con cartas boca abajo', function () {
        $sesion = sesionLista();
        $this->actingAs($this->tarotista)->postJson(route('sesiones.mezclar', $sesion));
        foreach ([1, 2, 3] as $indice) {
            $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => $indice]);
        }

        $this->actingAs($this->tarotista)->postJson(route('sesiones.revelar', $sesion), ['posicion' => 0])->assertNoContent();
        $this->actingAs($this->tarotista)->postJson(route('sesiones.revelar', $sesion), ['posicion' => 0])->assertConflict();
        $this->actingAs($this->tarotista)->postJson(route('sesiones.finalizar', $sesion))->assertConflict();
    });
});

it('valida el índice del abanico y la posición', function () {
    $sesion = sesionLista();
    $this->actingAs($this->tarotista)->postJson(route('sesiones.mezclar', $sesion));

    $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => 78])->assertUnprocessable();
    $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $sesion), ['indice_abanico' => -1])->assertUnprocessable();
    $this->actingAs($this->tarotista)->postJson(route('sesiones.revelar', $sesion), ['posicion' => 3])->assertUnprocessable();
});
