<?php

use App\Enums\EstadoTurno;
use App\Events\ColaActualizada;
use App\Events\TurnoCancelado;
use App\Events\TurnoCreado;
use App\Events\TurnoTomado;
use App\Events\TuTarotistaTeEspera;
use App\Models\Consulta;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([TurnoCreado::class, TurnoTomado::class, TurnoCancelado::class, ColaActualizada::class, TuTarotistaTeEspera::class]);
    $this->tarotista = User::factory()->tarotista()->create(['name' => 'Valeria', 'disponible' => true]);
});

describe('pedir turno', function () {
    it('una visitante entra a la fila solo con su nombre', function () {
        $this->post(route('turnos.store'), ['nombre' => 'María', 'motivo' => 'Trabajo nuevo'])->assertRedirect(route('espera'));

        $maria = User::where('name', 'María')->sole();
        $turno = Turno::sole();
        expect($maria->esInvitada())->toBeTrue()
            ->and($maria->esConsultante())->toBeTrue()
            ->and($turno->consultante_id)->toBe($maria->id)
            ->and($turno->estado)->toBe(EstadoTurno::Esperando)
            ->and($turno->motivo)->toBe('Trabajo nuevo');
        $this->assertAuthenticatedAs($maria);
        Event::assertDispatched(TurnoCreado::class);
        Event::assertDispatched(ColaActualizada::class);
    });

    it('sin login el nombre es obligatorio', function () {
        $this->post(route('turnos.store'), ['motivo' => 'x'])->assertSessionHasErrors('nombre');
        expect(Turno::count())->toBe(0);
    });

    it('si ya está esperando no se duplica el turno', function () {
        $this->post(route('turnos.store'), ['nombre' => 'María']);
        $this->post(route('turnos.store'), ['motivo' => 'otra vez']);

        expect(Turno::count())->toBe(1);
    });

    it('una tarotista no puede pedir turno', function () {
        $this->actingAs($this->tarotista)->post(route('turnos.store'), ['motivo' => 'x'])->assertForbidden();
    });
});

describe('salir de la fila', function () {
    it('la dueña sale y se avisa a las tarotistas y a la fila', function () {
        $turno = Turno::factory()->create();

        $this->actingAs($turno->consultante)->post(route('turnos.cancelar', $turno))->assertRedirect(route('espera'));

        expect($turno->fresh()->estado)->toBe(EstadoTurno::Cancelado);
        Event::assertDispatched(TurnoCancelado::class);
        Event::assertDispatched(ColaActualizada::class);
    });

    it('conocer el uuid (es público) no alcanza: nadie más puede sacarla de la fila', function () {
        $turno = Turno::factory()->create();

        $this->actingAs(User::factory()->invitada()->create())->post(route('turnos.cancelar', $turno))->assertForbidden();
        $this->actingAs($this->tarotista)->post(route('turnos.cancelar', $turno))->assertForbidden();
        expect($turno->fresh()->estado)->toBe(EstadoTurno::Esperando);
    });
});

describe('atender', function () {
    it('crea la consulta y avisa a todas: panel, fila y la consultante por su canal privado', function () {
        $turno = Turno::factory()->create();

        $respuesta = $this->actingAs($this->tarotista)->postJson(route('turnos.tomar', $turno))->assertOk();

        $consulta = Consulta::sole();
        expect($respuesta->json('url'))->toBe(route('consultas.show', $consulta))
            ->and($consulta->tarotista_id)->toBe($this->tarotista->id)
            ->and($consulta->consultante_id)->toBe($turno->consultante_id)
            ->and($turno->fresh()->estado)->toBe(EstadoTurno::Tomado)
            ->and($turno->fresh()->tomado_at)->not->toBeNull();
        Event::assertDispatched(TurnoTomado::class);
        Event::assertDispatched(ColaActualizada::class);
        Event::assertDispatched(TuTarotistaTeEspera::class);
    });

    it('si otra ya lo tomó: 409 con su nombre y no se crea otra consulta', function () {
        $turno = Turno::factory()->create();
        $sofia = User::factory()->tarotista()->create(['name' => 'Sofía', 'disponible' => true]);
        $this->actingAs($sofia)->postJson(route('turnos.tomar', $turno))->assertOk();

        $this->actingAs($this->tarotista)->postJson(route('turnos.tomar', $turno))
            ->assertConflict()
            ->assertJsonPath('message', 'Lo tomó Sofía.');
        expect(Consulta::count())->toBe(1);
    });

    it('hay que estar disponible', function () {
        $this->tarotista->forceFill(['disponible' => false])->save();
        $turno = Turno::factory()->create();

        $this->actingAs($this->tarotista)->postJson(route('turnos.tomar', $turno))->assertConflict();
        expect($turno->fresh()->estado)->toBe(EstadoTurno::Esperando);
    });

    it('no se atienden dos consultas a la vez', function () {
        [$uno, $dos] = Turno::factory()->count(2)->create();
        $this->actingAs($this->tarotista)->postJson(route('turnos.tomar', $uno))->assertOk();

        $this->actingAs($this->tarotista)->postJson(route('turnos.tomar', $dos))
            ->assertConflict()
            ->assertJsonPath('message', 'Ya tenés una consulta en curso.');
    });

    it('un turno cancelado no se puede atender', function () {
        $turno = Turno::factory()->create(['estado' => EstadoTurno::Cancelado]);

        $this->actingAs($this->tarotista)->postJson(route('turnos.tomar', $turno))->assertConflict();
    });

    it('una consultante no puede atender', function () {
        $turno = Turno::factory()->create();

        $this->actingAs(User::factory()->invitada()->create())->postJson(route('turnos.tomar', $turno))->assertForbidden();
    });
});

it('la tarotista prende y apaga "Disponible"', function () {
    $this->actingAs($this->tarotista)->postJson(route('atencion.disponible'), ['disponible' => false])->assertNoContent();
    expect($this->tarotista->fresh()->disponible)->toBeFalse();

    $this->actingAs(User::factory()->invitada()->create())->postJson(route('atencion.disponible'), ['disponible' => true])->assertForbidden();
});
