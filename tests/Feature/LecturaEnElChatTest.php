<?php

use App\Enums\EstadoLectura;
use App\Enums\EstadoPedido;
use App\Enums\TipoLectura;
use App\Enums\TipoMensaje;
use App\Events\CartaAstralCalculada;
use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\DatosNacimientoRecibidos;
use App\Events\EstadoCambiado;
use App\Events\LecturaIniciada;
use App\Events\MazoMezclado;
use App\Events\MensajeEnviado;
use App\Events\PedidoResuelto;
use App\Models\Ciudad;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\Mensaje;
use Database\Seeders\CartaSeeder;
use Database\Seeders\CiudadSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([
        MensajeEnviado::class, PedidoResuelto::class, LecturaIniciada::class, EstadoCambiado::class,
        MazoMezclado::class, CartaElegida::class, CartaRevelada::class,
        DatosNacimientoRecibidos::class, CartaAstralCalculada::class,
    ]);
    $this->consulta = Consulta::factory()->create();
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
});

function pedirOtra(string $servicio = 'si_no')
{
    return test()->actingAs(test()->consultante)->postJson(route('consultas.pedidos', test()->consulta), ['servicio' => $servicio, 'pregunta' => null]);
}

function elPedido(): Mensaje
{
    return Mensaje::where('tipo', TipoMensaje::PedidoServicio)->latest('id')->firstOrFail();
}

describe('la lectura vive en el chat', function () {
    it('cada carta revelada es un mensaje con la carta y su significado', function () {
        $this->seed(CartaSeeder::class);
        $lectura = Lectura::factory()->for($this->consulta)->create(); // tres cartas
        $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $lectura));
        foreach ([4, 9, 20] as $indice) {
            $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $lectura), ['indice_abanico' => $indice]);
        }

        $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $lectura), ['posicion' => 1])->assertNoContent();

        $mensaje = Mensaje::where('tipo', TipoMensaje::Carta)->sole();
        $carta = $lectura->cartas()->where('posicion', 1)->sole();
        $publico = $mensaje->fresh()->datosPublicos();
        expect($mensaje->lectura_id)->toBe($lectura->id)
            ->and($publico['lectura'])->toBe($lectura->id)
            ->and($publico['revelada'])->toMatchArray([
                'posicion' => 1,
                'nombre_posicion' => 'Presente',
                'invertida' => $carta->invertida,
            ])
            ->and($publico['revelada']['carta']['nombre'])->toBe($carta->carta->nombre);

        // Viaja por el canal y está en el historial del chat (al recargar).
        Event::assertDispatched(MensajeEnviado::class, fn ($e) => ($e->broadcastWith()['revelada']['posicion'] ?? null) === 1);
        $historial = $this->actingAs($this->consultante)->getJson(route('consultas.mensajes', $this->consulta))->json();
        expect(collect($historial)->firstWhere('tipo', 'carta')['revelada']['carta']['nombre'])->toBe($carta->carta->nombre);
    });

    it('la carta astral calculada es un mensaje con la tríada, sin los datos de nacimiento', function () {
        $this->seed(CiudadSeeder::class);
        $lectura = Lectura::factory()->for($this->consulta)->tipo(TipoLectura::CartaAstral)->create();

        $this->actingAs($this->consultante)->postJson(rutaLectura('nacimiento', $lectura), [
            'fecha' => '1990-01-15', 'hora' => '14:30', 'ciudad_id' => Ciudad::firstWhere('nombre', 'Formosa')->id,
        ])->assertNoContent();

        $publico = Mensaje::where('tipo', TipoMensaje::CartaAstral)->sole()->datosPublicos();
        $json = json_encode($publico);
        expect($publico['lectura'])->toBe($lectura->id)
            ->and($publico['resultado']['puntos'])->toHaveKeys(['sol', 'luna', 'ascendente'])
            ->and($json)->not->toContain('1990')->not->toContain('14:30')->not->toContain('Formosa');
    });
});

describe('pedir otra lectura sin esperar', function () {
    it('con la lectura terminada se puede pedir otra, y al aceptarla la anterior se cierra sola', function () {
        $anterior = Lectura::factory()->for($this->consulta)->create(['estado' => EstadoLectura::Abierta]);

        pedirOtra()->assertNoContent();
        $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, elPedido()]))->assertNoContent();

        expect($anterior->fresh()->estado)->toBe(EstadoLectura::Cerrada)
            ->and($anterior->fresh()->cerrada_at)->not->toBeNull()
            ->and($this->consulta->fresh()->lecturaActiva->tipo)->toBe(TipoLectura::SiNo);
        Event::assertDispatched(EstadoCambiado::class, fn ($e) => $e->broadcastWith() === ['lectura' => $anterior->id, 'estado' => 'cerrada']);
        Event::assertDispatched(LecturaIniciada::class);
    });

    it('también con la carta astral ya calculada', function () {
        Lectura::factory()->for($this->consulta)->tipo(TipoLectura::CartaAstral)->create(['estado' => EstadoLectura::Calculada]);

        pedirOtra('tres_cartas')->assertNoContent();
    });

    it('con la lectura a medias, no', function (EstadoLectura $estado) {
        Lectura::factory()->for($this->consulta)->create(['estado' => $estado]);

        pedirOtra()->assertConflict();
    })->with([EstadoLectura::Lista, EstadoLectura::Eligiendo, EstadoLectura::Revelando]);
});

describe('cancelar el pedido', function () {
    it('la consultante cancela su pedido pendiente y puede pedir otra cosa', function () {
        pedirOtra('cruz_celta');
        $pedido = elPedido();

        $this->actingAs($this->consultante)->postJson(route('consultas.pedidos.cancelar', [$this->consulta, $pedido]))->assertNoContent();

        expect($pedido->fresh()->estado_pedido)->toBe(EstadoPedido::Cancelado);
        Event::assertDispatched(PedidoResuelto::class, fn ($e) => $e->broadcastWith() === ['mensaje' => $pedido->id, 'estado_pedido' => 'cancelado']);
        pedirOtra('tres_cartas')->assertNoContent();
    });

    it('un pedido ya respondido no se cancela, ni se acepta uno cancelado', function () {
        pedirOtra();
        $pedido = elPedido();
        $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.rechazar', [$this->consulta, $pedido]));

        $this->actingAs($this->consultante)->postJson(route('consultas.pedidos.cancelar', [$this->consulta, $pedido]))->assertConflict();

        pedirOtra('tres_cartas');
        $otro = elPedido();
        $this->actingAs($this->consultante)->postJson(route('consultas.pedidos.cancelar', [$this->consulta, $otro]));
        $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.aceptar', [$this->consulta, $otro]))->assertConflict();
        expect(Lectura::count())->toBe(0);
    });

    it('la tarotista no cancela pedidos', function () {
        pedirOtra();

        $this->actingAs($this->tarotista)->postJson(route('consultas.pedidos.cancelar', [$this->consulta, elPedido()]))->assertForbidden();
    });
});

it('el texto corto de la carta queda para el resumen', function () {
    $this->seed(CartaSeeder::class);
    $lectura = Lectura::factory()->for($this->consulta)->tipo(TipoLectura::SiNo)->create();
    $this->actingAs($this->tarotista)->postJson(rutaLectura('mezclar', $lectura));
    $this->actingAs($this->consultante)->postJson(rutaLectura('elegir', $lectura), ['indice_abanico' => 7]);
    $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $lectura), ['posicion' => 0]);

    $carta = $lectura->cartas()->sole();
    expect(Mensaje::where('tipo', TipoMensaje::Carta)->sole()->contenido)
        ->toBe('Respuesta: '.$carta->carta->nombre.($carta->invertida ? ' (invertida)' : ''));
    $this->actingAs($this->consultante)->get(route('consultas.resumen', $this->consulta))->assertSee($carta->carta->nombre);
});
