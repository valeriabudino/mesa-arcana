<?php

use App\Enums\EstadoCobro;
use App\Enums\EstadoPedido;
use App\Enums\TipoMovimiento;
use App\Events\SaldoActualizado;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\Mensaje;
use App\Models\Movimiento;
use App\Models\Pregunta;
use App\Models\User;
use App\Services\BilleteraService;
use App\Services\ColaService;
use Database\Seeders\CartaSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Queue::fake(); // la interpretación con IA no corre acá
    config(['creditos.regalo_inicial' => 100, 'creditos.comision' => 20, 'creditos.precios.si_no' => 10, 'creditos.precios.tres_cartas' => 20]);
    $this->consulta = Consulta::factory()->create();
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
    $this->siNo = Pregunta::factory()->siNo()->create();
});

/** Pide un sí o no y la tarotista lo acepta: devuelve la lectura nueva. */
function pedirYAceptarSiNo(): Lectura
{
    test()->actingAs(test()->consultante)
        ->postJson(route('consultas.pedidos', test()->consulta), ['servicio' => 'si_no', 'pregunta_id' => test()->siNo->id])
        ->assertNoContent();
    $pedido = Mensaje::where('estado_pedido', EstadoPedido::Pendiente)->sole();
    test()->actingAs(test()->tarotista)->postJson(route('consultas.pedidos.aceptar', [test()->consulta, $pedido]))->assertNoContent();

    return Lectura::latest('id')->first();
}

/** Mezclar, elegir y revelar la única carta del sí o no: la lectura queda Abierta. */
function terminarSiNo(Lectura $lectura): void
{
    test()->actingAs(test()->tarotista)->postJson(rutaLectura('mezclar', $lectura))->assertSuccessful();
    test()->actingAs(test()->consultante)->postJson(rutaLectura('elegir', $lectura), ['indice_abanico' => 5])->assertSuccessful();
    test()->actingAs(test()->tarotista)->postJson(rutaLectura('revelar', $lectura), ['posicion' => 0])->assertSuccessful();
}

/** [disponible, reservado] */
function saldoDe(User $user): array
{
    $user->refresh();

    return [$user->saldo_disponible, $user->saldo_reservado];
}

function sistema(): array
{
    return Mensaje::where('tipo', 'sistema')->pluck('contenido')->all();
}

it('quien entra a consultar recibe el regalo, con su movimiento', function () {
    $invitada = app(ColaService::class)->crearInvitada('Ana');

    expect(saldoDe($invitada))->toBe([100, 0])
        ->and(Movimiento::where('user_id', $invitada->id)->sole())
        ->tipo->toBe(TipoMovimiento::Regalo)
        ->monto->toBe(100)
        ->saldo_disponible->toBe(100);
});

it('al aceptar el pedido se reservan los créditos', function () {
    $lectura = pedirYAceptarSiNo();

    expect(saldoDe($this->consultante))->toBe([90, 10])
        ->and($lectura->precio)->toBe(10)
        ->and($lectura->estado_cobro)->toBe(EstadoCobro::Reservado)
        ->and(sistema())->toContain('Se reservaron 10 créditos: se cobran al cerrar la lectura.');
});

it('al cerrar la lectura se cobra: pasa a la tarotista menos la comisión', function () {
    $lectura = pedirYAceptarSiNo();
    terminarSiNo($lectura);

    $this->actingAs($this->tarotista)->postJson(rutaLectura('cerrar', $lectura))->assertSuccessful();

    expect(saldoDe($this->consultante))->toBe([90, 0])
        ->and(saldoDe($this->tarotista))->toBe([8, 0])
        ->and($lectura->fresh()->estado_cobro)->toBe(EstadoCobro::Cobrado)
        ->and(Movimiento::where('user_id', $this->tarotista->id)->pluck('monto', 'tipo')->all())
        ->toBe(['ingreso' => 10, 'comision' => -2])
        ->and(sistema())->toContain('Se cobraron 10 créditos de la lectura.');
});

it('cobrar dos veces no cobra dos veces', function () {
    $lectura = pedirYAceptarSiNo();
    terminarSiNo($lectura);
    $this->actingAs($this->tarotista)->postJson(rutaLectura('cerrar', $lectura));

    expect(app(BilleteraService::class)->cobrar($lectura))->toBeNull()
        ->and(app(BilleteraService::class)->liberar($lectura))->toBeNull()
        ->and(saldoDe($this->tarotista))->toBe([8, 0]);
});

it('aceptar otra lectura con la anterior terminada cobra la anterior y reserva la nueva', function () {
    $primera = pedirYAceptarSiNo();
    terminarSiNo($primera);

    $segunda = pedirYAceptarSiNo();

    expect($primera->fresh()->estado_cobro)->toBe(EstadoCobro::Cobrado)
        ->and($segunda->estado_cobro)->toBe(EstadoCobro::Reservado)
        ->and(saldoDe($this->consultante))->toBe([80, 10])
        ->and(saldoDe($this->tarotista))->toBe([8, 0]);
});

it('si la consulta termina con la lectura a medias, los créditos vuelven', function () {
    pedirYAceptarSiNo(); // aceptada pero sin mezclar

    $this->actingAs($this->tarotista)->postJson(route('consultas.finalizar', $this->consulta))->assertNoContent();

    expect(saldoDe($this->consultante))->toBe([100, 0])
        ->and(saldoDe($this->tarotista))->toBe([0, 0])
        ->and(Lectura::sole()->estado_cobro)->toBe(EstadoCobro::Liberado)
        ->and(sistema())->toContain('La lectura quedó sin terminar: se devolvieron 10 créditos.');
});

it('si la consulta termina con la lectura terminada, se cobra', function () {
    terminarSiNo(pedirYAceptarSiNo());

    $this->actingAs($this->tarotista)->postJson(route('consultas.finalizar', $this->consulta))->assertNoContent();

    expect(saldoDe($this->consultante))->toBe([90, 0])
        ->and(saldoDe($this->tarotista))->toBe([8, 0]);
});

it('sin saldo suficiente no se puede pedir', function () {
    config(['creditos.precios.si_no' => 500]);

    $this->actingAs($this->consultante)
        ->postJson(route('consultas.pedidos', $this->consulta), ['servicio' => 'si_no', 'pregunta_id' => $this->siNo->id])
        ->assertConflict()
        ->assertJson(['message' => 'No te alcanzan los créditos: Sí o no cuesta 500 y tenés 100.']);

    expect(Mensaje::whereNotNull('estado_pedido')->count())->toBe(0);
});

it('si el saldo bajó antes de aceptar, no se acepta y no cambia nada', function () {
    $this->actingAs($this->consultante)
        ->postJson(route('consultas.pedidos', $this->consulta), ['servicio' => 'si_no', 'pregunta_id' => $this->siNo->id]);
    config(['creditos.precios.si_no' => 500]); // como si el precio subiera entre el pedido y la respuesta
    $pedido = Mensaje::where('estado_pedido', EstadoPedido::Pendiente)->sole();

    $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, $pedido]))->assertConflict();

    expect(Lectura::count())->toBe(0)
        ->and($pedido->fresh()->estado_pedido)->toBe(EstadoPedido::Pendiente)
        ->and(saldoDe($this->consultante))->toBe([100, 0]);
});

it('el saldo siempre es la suma de sus movimientos', function () {
    $primera = pedirYAceptarSiNo();
    terminarSiNo($primera);
    pedirYAceptarSiNo();
    $this->actingAs($this->tarotista)->postJson(route('consultas.finalizar', $this->consulta));

    foreach ([$this->consultante, $this->tarotista] as $user) {
        $movimientos = Movimiento::where('user_id', $user->id);
        expect(saldoDe($user))->toBe([(int) $movimientos->sum('monto'), (int) $movimientos->sum('monto_reservado')]);
    }
});

it('cada cambio de saldo se avisa por el canal privado de su dueña', function () {
    Event::fake([SaldoActualizado::class]);

    pedirYAceptarSiNo();

    Event::assertDispatched(SaldoActualizado::class, fn (SaldoActualizado $e) => $e->broadcastOn()[0]->name === "private-App.Models.User.{$this->consultante->id}"
        && $e->broadcastWith() === ['disponible' => 90, 'reservado' => 10]);
});

it('/estado trae el saldo propio, para ponerse al día al reconectar', function () {
    pedirYAceptarSiNo();

    $this->actingAs($this->consultante)->getJson(route('consultas.estado', $this->consulta))
        ->assertJsonPath('saldo', ['disponible' => 90, 'reservado' => 10]);
    $this->actingAs($this->tarotista)->getJson(route('consultas.estado', $this->consulta))
        ->assertJsonPath('saldo', ['disponible' => 0, 'reservado' => 0]);
});
