<?php

use App\Enums\EstadoConsulta;
use App\Enums\EstadoLectura;
use App\Enums\EstadoTurno;
use App\Events\ConsultaFinalizada;
use App\Events\EstadoCambiado;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\Turno;
use Database\Seeders\CartaSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([EstadoCambiado::class, ConsultaFinalizada::class]);
    $this->consulta = Consulta::factory()->create();
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
    $this->lectura = Lectura::factory()->for($this->consulta)->create();
});

/** Mezcla, elige 3 y revela las que se indiquen. */
function tirada(array $revelar): void
{
    test()->actingAs(test()->tarotista)->postJson(rutaLectura('mezclar', test()->lectura));
    foreach ([4, 8, 15] as $indice) {
        test()->actingAs(test()->consultante)->postJson(rutaLectura('elegir', test()->lectura), ['indice_abanico' => $indice]);
    }
    foreach ($revelar as $posicion) {
        test()->actingAs(test()->tarotista)->postJson(rutaLectura('revelar', test()->lectura), ['posicion' => $posicion]);
    }
}

it('cerrar lectura: solo cuando está Abierta, y libera la mesa para otra', function () {
    tirada([0, 1]);
    $this->actingAs($this->tarotista)->postJson(rutaLectura('cerrar', $this->lectura))->assertConflict(); // todavía Revelando

    $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 2]);
    $this->actingAs($this->tarotista)->postJson(rutaLectura('cerrar', $this->lectura))->assertNoContent();

    expect($this->lectura->fresh()->estado)->toBe(EstadoLectura::Cerrada)
        ->and($this->consulta->fresh()->lecturaActiva)->toBeNull();
    Event::assertDispatched(EstadoCambiado::class, fn ($e) => $e->broadcastWith() === ['lectura' => $this->lectura->id, 'estado' => 'cerrada']);
});

it('la consultante no cierra lecturas', function () {
    tirada([0, 1, 2]);
    $this->actingAs($this->consultante)->postJson(rutaLectura('cerrar', $this->lectura))->assertForbidden();
});

it('finalizar con una lectura a medias la interrumpe y el resumen muestra SOLO lo revelado', function () {
    tirada([1]); // reveló solo la del medio
    $reveladas = $this->lectura->cartas()->with('carta')->whereNotNull('revelada_at')->get();
    $ocultas = $this->lectura->cartas()->with('carta')->whereNull('revelada_at')->get();

    $this->actingAs($this->tarotista)->postJson(route('consultas.finalizar', $this->consulta))->assertNoContent();

    expect($this->consulta->fresh()->estado)->toBe(EstadoConsulta::Finalizada)
        ->and($this->lectura->fresh()->estado)->toBe(EstadoLectura::Cerrada);
    Event::assertDispatched(ConsultaFinalizada::class);

    $resumen = $this->actingAs($this->consultante)->get(route('consultas.resumen', $this->consulta))->assertOk();
    $reveladas->each(fn ($e) => $resumen->assertSee($e->carta->nombre));
    $ocultas->each(fn ($e) => $resumen->assertDontSee($e->carta->nombre)->assertDontSee($e->carta->imagen));
});

it('después de finalizar, la tarotista puede atender a otra persona', function () {
    $this->tarotista->forceFill(['disponible' => true])->save();
    $this->actingAs($this->tarotista)->postJson(route('consultas.finalizar', $this->consulta));

    $this->actingAs($this->tarotista)->postJson(route('turnos.tomar', Turno::factory()->create()))->assertOk();
});

it('una consulta finalizada no se vuelve a finalizar y solo la tarotista finaliza', function () {
    $this->actingAs($this->consultante)->postJson(route('consultas.finalizar', $this->consulta))->assertForbidden();
    $this->actingAs($this->tarotista)->postJson(route('consultas.finalizar', $this->consulta))->assertNoContent();
    $this->actingAs($this->tarotista)->postJson(route('consultas.finalizar', $this->consulta))->assertConflict();
});

describe('ausente', function () {
    beforeEach(function () {
        $this->lectura->delete(); // la consultante nunca llegó: no hay lecturas
    });

    it('recién después de los minutos de espera', function () {
        $this->actingAs($this->tarotista)->postJson(route('consultas.ausente', $this->consulta))->assertConflict();

        $this->travel(3)->minutes();
        $this->actingAs($this->tarotista)->postJson(route('consultas.ausente', $this->consulta))->assertNoContent();

        expect($this->consulta->turno->fresh()->estado)->toBe(EstadoTurno::Ausente)
            ->and($this->consulta->fresh()->estado)->toBe(EstadoConsulta::Finalizada);
    });

    it('no si la consultante ya participó', function () {
        $this->actingAs($this->consultante)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => 'Hola, llegué']);
        $this->travel(3)->minutes();

        $this->actingAs($this->tarotista)->postJson(route('consultas.ausente', $this->consulta))->assertConflict();
    });
});
