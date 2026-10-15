<?php

use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\User;

beforeEach(function () {
    $this->consulta = Consulta::factory()->create();
    $this->lectura = Lectura::factory()->for($this->consulta)->create();
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
    $this->otraTarotista = User::factory()->tarotista()->create();
    $this->otraConsultante = User::factory()->consultante()->create();
});

it('solo las dos de la consulta ven la sala', function () {
    expect($this->tarotista->can('ver', $this->consulta))->toBeTrue()
        ->and($this->consultante->can('ver', $this->consulta))->toBeTrue()
        ->and($this->otraTarotista->can('ver', $this->consulta))->toBeFalse()
        ->and($this->otraConsultante->can('ver', $this->consulta))->toBeFalse();
});

it('solo la tarotista de la consulta mezcla y revela', function (string $accion) {
    expect($this->tarotista->can($accion, $this->lectura))->toBeTrue()
        ->and($this->otraTarotista->can($accion, $this->lectura))->toBeFalse()
        ->and($this->consultante->can($accion, $this->lectura))->toBeFalse();
})->with(['mezclar', 'revelar']);

it('solo la consultante de la consulta elige', function () {
    expect($this->consultante->can('elegir', $this->lectura))->toBeTrue()
        ->and($this->otraConsultante->can('elegir', $this->lectura))->toBeFalse()
        ->and($this->tarotista->can('elegir', $this->lectura))->toBeFalse();
});
