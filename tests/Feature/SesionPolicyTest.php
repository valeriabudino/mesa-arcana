<?php

use App\Enums\EstadoSesion;
use App\Exceptions\AccionFueraDeOrden;
use App\Models\Sesion;
use App\Models\User;
use App\Policies\SesionPolicy;

beforeEach(function () {
    $this->consultante = User::factory()->consultante()->create();
    $this->sesion = Sesion::factory()->conConsultante($this->consultante)->create();
    $this->tarotista = $this->sesion->tarotista;
    $this->otraTarotista = User::factory()->tarotista()->create();
    $this->otraConsultante = User::factory()->consultante()->create();
});

it('solo la tarotista dueña mezcla, revela y finaliza', function (string $accion) {
    expect($this->tarotista->can($accion, $this->sesion))->toBeTrue()
        ->and($this->otraTarotista->can($accion, $this->sesion))->toBeFalse()
        ->and($this->consultante->can($accion, $this->sesion))->toBeFalse();
})->with(['mezclar', 'revelar', 'finalizar']);

it('solo la consultante de la sesión elige', function () {
    expect($this->consultante->can('elegir', $this->sesion))->toBeTrue()
        ->and($this->otraConsultante->can('elegir', $this->sesion))->toBeFalse()
        ->and($this->tarotista->can('elegir', $this->sesion))->toBeFalse();
});

it('solo las tarotistas crean sesiones', function () {
    expect($this->tarotista->can('create', Sesion::class))->toBeTrue()
        ->and($this->consultante->can('create', Sesion::class))->toBeFalse();
});

it('una consultante cualquiera entra solo si la sesión no tiene consultante', function () {
    $libre = Sesion::factory()->create();

    expect($this->otraConsultante->can('entrar', $libre))->toBeTrue()
        ->and($this->otraConsultante->can('entrar', $this->sesion))->toBeFalse()
        ->and($this->otraTarotista->can('entrar', $libre))->toBeFalse()
        ->and($this->consultante->can('entrar', $this->sesion))->toBeTrue();
});

it('una visitante sin login puede entrar e ingresar solo si la sesión está libre', function () {
    $libre = Sesion::factory()->create();
    $policy = new SesionPolicy;

    expect($policy->entrar(null, $libre))->toBeTrue()
        ->and($policy->ingresar(null, $libre))->toBeTrue()
        ->and($policy->entrar(null, $this->sesion))->toBeFalse()
        ->and($policy->ingresar(null, $this->sesion))->toBeFalse()
        ->and($policy->ingresar($this->otraConsultante, $libre))->toBeFalse(); // con login no se usa el formulario
});

it('pasarA cambia el estado si la transición es válida', function () {
    $this->sesion->pasarA(EstadoSesion::Eligiendo);

    expect($this->sesion->estado)->toBe(EstadoSesion::Eligiendo);
});

it('pasarA lanza AccionFueraDeOrden si la transición no es válida', function () {
    $this->sesion->pasarA(EstadoSesion::Finalizada);
})->throws(AccionFueraDeOrden::class);
