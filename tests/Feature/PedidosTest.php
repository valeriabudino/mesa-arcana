<?php

use App\Enums\EstadoLectura;
use App\Enums\EstadoPedido;
use App\Enums\TipoLectura;
use App\Events\LecturaIniciada;
use App\Events\PedidoResuelto;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\Mensaje;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([PedidoResuelto::class, LecturaIniciada::class]);
    $this->consulta = Consulta::factory()->create();
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
});

function pedir(string $servicio = 'tres_cartas', ?string $pregunta = '¿Me mudo?')
{
    return test()->actingAs(test()->consultante)->postJson(route('consultas.pedidos', test()->consulta), ['servicio' => $servicio, 'pregunta' => $pregunta]);
}

function pedidoPendiente(): Mensaje
{
    return Mensaje::where('estado_pedido', EstadoPedido::Pendiente)->sole();
}

it('la consultante pide, la tarotista acepta y nace la lectura', function () {
    pedir()->assertNoContent();
    $pedido = pedidoPendiente();
    expect($pedido->servicio)->toBe(TipoLectura::TresCartas)->and($pedido->contenido)->toBe('¿Me mudo?');

    $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, $pedido]))->assertNoContent();

    $lectura = Lectura::sole();
    expect($pedido->fresh()->estado_pedido)->toBe(EstadoPedido::Aceptado)
        ->and($lectura->tipo)->toBe(TipoLectura::TresCartas)
        ->and($lectura->pregunta)->toBe('¿Me mudo?')
        ->and($lectura->estado)->toBe(EstadoLectura::Lista);
    Event::assertDispatched(PedidoResuelto::class);
    Event::assertDispatched(LecturaIniciada::class, fn (LecturaIniciada $e) => $e->broadcastWith() === ['lectura' => $lectura->fresh()->datosPublicos()]);
});

it('la tarotista puede rechazarlo', function () {
    pedir();
    $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.rechazar', [$this->consulta, pedidoPendiente()]))->assertNoContent();

    expect(Mensaje::where('estado_pedido', EstadoPedido::Rechazado)->count())->toBe(1)
        ->and(Lectura::count())->toBe(0);
});

describe('409: no es el momento', function () {
    it('no se pide con un pedido esperando respuesta', function () {
        pedir();
        pedir('si_no')->assertConflict();
    });

    it('no se pide con una lectura en curso', function () {
        Lectura::factory()->for($this->consulta)->create();
        pedir()->assertConflict();
    });

    it('un pedido no se responde dos veces', function () {
        pedir();
        $pedido = pedidoPendiente();
        $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, $pedido]))->assertNoContent();

        $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.rechazar', [$this->consulta, $pedido]))->assertConflict();
        expect(Lectura::count())->toBe(1);
    });

    it('la carta astral todavía no se puede pedir', function () {
        pedir('carta_astral')->assertConflict();
    });
});

describe('403/404', function () {
    it('la tarotista no pide y la consultante no responde', function () {
        $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos', $this->consulta), ['servicio' => 'si_no'])->assertForbidden();
        pedir();
        $this->actingAs($this->consultante)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, pedidoPendiente()]))->assertForbidden();
    });

    it('un pedido de otra consulta no se alcanza por la URL de esta', function () {
        $ajeno = Mensaje::factory()->create(['estado_pedido' => EstadoPedido::Pendiente]);

        $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, $ajeno]))->assertNotFound();
    });
});

it('valida el servicio', function () {
    pedir('tarot_de_feria')->assertUnprocessable();
});
