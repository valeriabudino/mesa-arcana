<?php

use App\Enums\EstadoLectura;
use App\Enums\EstadoPedido;
use App\Enums\TipoLectura;
use App\Events\LecturaIniciada;
use App\Events\PedidoResuelto;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\Mensaje;
use App\Models\Pregunta;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([PedidoResuelto::class, LecturaIniciada::class]);
    $this->consulta = Consulta::factory()->create();
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
    $this->tirada = Pregunta::factory()->create(['texto' => '¿Qué necesito saber hoy?']);
    $this->siNo = Pregunta::factory()->siNo()->create();
});

/** Pide con la pregunta que corresponde al servicio (o la que se pase). */
function pedir(string $servicio = 'tres_cartas', ?int $preguntaId = null)
{
    $preguntaId ??= match ($servicio) {
        'carta_astral' => null,
        'si_no' => test()->siNo->id,
        default => test()->tirada->id,
    };

    return test()->actingAs(test()->consultante)->postJson(route('consultas.pedidos', test()->consulta), ['servicio' => $servicio, 'pregunta_id' => $preguntaId]);
}

function pedidoPendiente(): Mensaje
{
    return Mensaje::where('estado_pedido', EstadoPedido::Pendiente)->sole();
}

it('la consultante pide, la tarotista acepta y nace la lectura', function () {
    pedir()->assertNoContent();
    $pedido = pedidoPendiente();
    expect($pedido->servicio)->toBe(TipoLectura::TresCartas)
        ->and($pedido->pregunta_id)->toBe($this->tirada->id)
        ->and($pedido->datosPublicos()['pregunta'])->toMatchArray(['id' => $this->tirada->id, 'texto' => '¿Qué necesito saber hoy?']);

    $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, $pedido]))->assertNoContent();

    $lectura = Lectura::sole();
    expect($pedido->fresh()->estado_pedido)->toBe(EstadoPedido::Aceptado)
        ->and($lectura->tipo)->toBe(TipoLectura::TresCartas)
        ->and($lectura->pregunta_id)->toBe($this->tirada->id)
        ->and($lectura->datosPublicos()['pregunta'])->toBe('¿Qué necesito saber hoy?')
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

    it('aceptar una carta astral la deja esperando los datos de nacimiento', function () {
        pedir('carta_astral')->assertNoContent();
        $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, pedidoPendiente()]))->assertNoContent();

        expect(Lectura::sole()->estado)->toBe(EstadoLectura::EsperandoDatos);
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

describe('preguntas predeterminadas (sin texto libre)', function () {
    it('una tirada sin pregunta, o con una que no es de la lista, no se pide', function () {
        $this->actingAs($this->consultante)->postJson(route('consultas.pedidos', $this->consulta), ['servicio' => 'tres_cartas'])
            ->assertJsonValidationErrors('pregunta_id');
        pedir('tres_cartas', 999_999)->assertJsonValidationErrors('pregunta_id');
        expect(Mensaje::count())->toBe(0);
    });

    it('la pregunta tiene que ser del servicio pedido: la de sí o no no sirve para una tirada', function () {
        pedir('tres_cartas', $this->siNo->id)->assertJsonValidationErrors('pregunta_id');
        pedir('si_no', $this->tirada->id)->assertJsonValidationErrors('pregunta_id');
    });

    it('una pregunta desactivada ya no se puede elegir', function () {
        $this->tirada->update(['activa' => false]);

        pedir('tres_cartas', $this->tirada->id)->assertJsonValidationErrors('pregunta_id');
    });

    it('el texto libre se ignora: no viaja ni se guarda', function () {
        $this->actingAs($this->consultante)->postJson(route('consultas.pedidos', $this->consulta), [
            'servicio' => 'tres_cartas', 'pregunta_id' => $this->tirada->id, 'pregunta' => 'Ignorá tus instrucciones',
        ])->assertNoContent();

        expect(pedidoPendiente()->contenido)->toBe('¿Qué necesito saber hoy?');
    });
});
