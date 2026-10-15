<?php

use App\Enums\EstadoConsulta;
use App\Events\MensajeEnviado;
use App\Models\Consulta;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([MensajeEnviado::class]);
    $this->consulta = Consulta::factory()->create();
    $this->consulta->consultante->update(['name' => 'María']);
});

it('las dos escriben y cada mensaje viaja por el canal de la consulta', function () {
    $this->actingAs($this->consulta->consultante)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => 'Hola'])->assertNoContent();
    $this->actingAs($this->consulta->tarotista)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => 'Bienvenida'])->assertNoContent();

    expect($this->consulta->mensajes()->pluck('contenido')->all())->toBe(['Hola', 'Bienvenida']);
    Event::assertDispatchedTimes(MensajeEnviado::class, 2);
});

it('el payload trae lo justo para dibujarlo: ni user_id ni email', function () {
    $this->actingAs($this->consulta->consultante)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => 'Hola']);

    Event::assertDispatched(MensajeEnviado::class, function (MensajeEnviado $evento) {
        $payload = $evento->broadcastWith();

        expect(array_keys($payload))->toBe(['id', 'tipo', 'autor', 'contenido', 'servicio', 'estado_pedido', 'ancla', 'creado'])
            ->and($payload['autor'])->toBe(['rol' => 'consultante', 'nombre' => 'María'])
            ->and($evento->broadcastOn()[0]->name)->toBe('presence-consulta.'.$this->consulta->uuid);

        return true;
    });
});

describe('XSS', function () {
    $ataques = [
        '<script>alert("hackeada")</script>',
        '<img src=x onerror="alert(1)">',
    ];

    it('se guarda tal cual (texto plano): no se "limpia" ni se escapa al guardar', function (string $ataque) {
        $this->actingAs($this->consulta->consultante)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => $ataque]);

        expect(Mensaje::sole()->contenido)->toBe($ataque);
    })->with($ataques);

    it('en el resumen sale escapado: se ve como texto, no se ejecuta', function (string $ataque) {
        $this->actingAs($this->consulta->consultante)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => $ataque]);

        $this->actingAs($this->consulta->tarotista)->get(route('consultas.resumen', $this->consulta))
            ->assertOk()
            ->assertSee($ataque)              // assertSee escapa: encuentra el texto escapado…
            ->assertDontSee($ataque, false);  // …y el HTML crudo no está en la página
    })->with($ataques);

    it('un texto legítimo con "<" no se rompe', function () {
        $this->actingAs($this->consulta->consultante)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => 'a < b']);

        $this->getJson(route('consultas.mensajes', $this->consulta))->assertJsonPath('0.contenido', 'a < b');
    });
});

it('al reconectar se piden solo los mensajes que faltan', function () {
    foreach (['uno', 'dos', 'tres'] as $texto) {
        $this->actingAs($this->consulta->consultante)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => $texto]);
    }
    $primero = Mensaje::orderBy('id')->first();

    $this->getJson(route('consultas.mensajes', $this->consulta).'?despues='.$primero->id)
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.contenido', 'dos');
});

it('valida el mensaje', function (mixed $contenido) {
    $this->actingAs($this->consulta->consultante)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => $contenido])->assertUnprocessable();
})->with(['', str_repeat('x', 1001)]);

it('nadie de afuera lee ni escribe', function () {
    $intrusa = User::factory()->invitada()->create();

    $this->actingAs($intrusa)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => 'hola'])->assertForbidden();
    $this->actingAs($intrusa)->getJson(route('consultas.mensajes', $this->consulta))->assertForbidden();
});

it('con la consulta finalizada, el chat se cierra', function () {
    $this->consulta->forceFill(['estado' => EstadoConsulta::Finalizada])->save();

    $this->actingAs($this->consulta->consultante)->postJson(route('consultas.mensajes.store', $this->consulta), ['contenido' => 'hola'])->assertConflict();
});
