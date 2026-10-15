<?php

use App\Enums\EstadoLectura;
use App\Enums\EstadoPedido;
use App\Enums\TipoLectura;
use App\Events\AclaratoriaAceptada;
use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\Mensaje;
use Database\Seeders\CartaSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([AclaratoriaAceptada::class, CartaElegida::class, CartaRevelada::class]);
    $this->consulta = Consulta::factory()->create();
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
    $this->lectura = Lectura::factory()->for($this->consulta)->create(); // tres cartas
});

/** Tirada de tres completa y revelada: la lectura queda Abierta. */
function tiradaAbierta(): void
{
    test()->actingAs(test()->tarotista)->postJson(rutaLectura('mezclar', test()->lectura));
    foreach ([4, 8, 15] as $indice) {
        test()->actingAs(test()->consultante)->postJson(rutaLectura('elegir', test()->lectura), ['indice_abanico' => $indice]);
    }
    foreach ([0, 1, 2] as $posicion) {
        test()->actingAs(test()->tarotista)->postJson(rutaLectura('revelar', test()->lectura), ['posicion' => $posicion]);
    }
}

function pedirAclarar(int $posicion)
{
    return test()->actingAs(test()->consultante)->postJson(rutaLectura('aclaratoria', test()->lectura), ['posicion' => $posicion]);
}

function aceptarPendiente()
{
    $pedido = Mensaje::where('estado_pedido', EstadoPedido::Pendiente)->sole();

    return test()->actingAs(test()->tarotista)->postJson(route('consultas.pedidos.aceptar', [test()->consulta, $pedido]));
}

it('recorre una aclaratoria: pedir, aceptar, elegir una más (del mismo mazo) y revelarla', function () {
    tiradaAbierta();
    $orden = $this->lectura->fresh()->orden_mazo;

    pedirAclarar(1)->assertNoContent();
    expect(Mensaje::where('estado_pedido', EstadoPedido::Pendiente)->sole()->datosPublicos()['ancla'])->toBe(['tipo' => 'carta', 'valor' => '1']);

    aceptarPendiente()->assertNoContent();
    $lectura = $this->lectura->fresh();
    expect($lectura->estado)->toBe(EstadoLectura::Eligiendo)
        ->and($lectura->cartas_esperadas)->toBe(4)
        ->and($lectura->aclarando)->toBe(1)
        ->and($lectura->orden_mazo)->toBe($orden); // no se remezcla
    Event::assertDispatched(AclaratoriaAceptada::class, fn ($e) => $e->broadcastWith() === ['lectura' => $lectura->id, 'aclara' => 1]);

    // Una carta ya elegida no se puede volver a elegir
    $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 8])->assertConflict();

    $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 30])->assertNoContent();
    $aclaratoria = $this->lectura->cartas()->where('posicion', 3)->sole();
    expect($aclaratoria->es_aclaratoria)->toBeTrue()
        ->and($aclaratoria->aclara_posicion)->toBe(1)
        ->and($aclaratoria->carta_id)->toBe($orden[30])
        ->and($this->lectura->fresh()->estado)->toBe(EstadoLectura::Revelando)
        ->and($this->lectura->fresh()->aclarando)->toBeNull();
    Event::assertDispatched(CartaElegida::class, fn ($e) => $e->broadcastWith() === ['lectura' => $this->lectura->id, 'posicion' => 3, 'indice_abanico' => 30, 'aclara' => 1]);

    $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 3])->assertNoContent();
    expect($this->lectura->fresh()->estado)->toBe(EstadoLectura::Abierta);
    Event::assertDispatched(CartaRevelada::class, fn ($e) => $e->broadcastWith()['nombre_posicion'] === 'Aclaración de «Presente»');

    $this->actingAs($this->consultante)->get(route('consultas.resumen', $this->consulta))->assertOk()->assertSee('Aclaración de «Presente»');
});

describe('cuándo no se puede', function () {
    it('antes de revelar todas', function () {
        $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $this->lectura));
        pedirAclarar(0)->assertConflict();
    });

    it('dos veces la misma carta', function () {
        tiradaAbierta();
        pedirAclarar(1);
        aceptarPendiente();
        $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 30]);
        $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 3]);

        pedirAclarar(1)->assertConflict()->assertJsonPath('message', 'Esa carta ya tiene su aclaratoria.');
    });

    it('aclarar una aclaratoria o una posición que no existe', function () {
        tiradaAbierta();
        pedirAclarar(7)->assertConflict();
    });

    it('en un sí o no', function () {
        $this->lectura->forceFill(['tipo' => TipoLectura::SiNo, 'cartas_esperadas' => 1])->save();
        $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $this->lectura));
        $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 3]);
        $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 0]);

        pedirAclarar(0)->assertConflict();
    });

    it('la tarotista no pide aclaratorias', function () {
        tiradaAbierta();
        $this->actingAs($this->tarotista)->postJson(rutaLectura('aclaratoria', $this->lectura), ['posicion' => 0])->assertForbidden();
    });
});

it('si la lectura se cierra con una aclaratoria pendiente, el pedido queda sin efecto y no traba otros pedidos', function () {
    // Lo que pasó en una prueba real: pidió aclarar y, segundos después, se cerró la lectura.
    tiradaAbierta();
    pedirAclarar(1)->assertNoContent();
    $pedido = Mensaje::where('estado_pedido', EstadoPedido::Pendiente)->sole();

    $this->actingAs($this->tarotista)->postJson(rutaLectura('cerrar', $this->lectura))->assertNoContent();

    expect($pedido->fresh()->estado_pedido)->toBe(EstadoPedido::Cancelado)
        ->and(Mensaje::where('contenido', 'El pedido de aclaratoria quedó sin efecto: la lectura se cerró.')->exists())->toBeTrue();
    $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, $pedido]))->assertConflict();
    $this->actingAs($this->consultante)->postJson(route('consultas.pedidos', $this->consulta), [
        'servicio' => 'carta_astral',
    ])->assertNoContent(); // ya no hay nada pendiente que lo trabe
});
