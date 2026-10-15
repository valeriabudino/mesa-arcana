<?php

use App\Models\Ciudad;
use Database\Seeders\CiudadSeeder;

beforeEach(fn () => $this->seed(CiudadSeeder::class));

it('tiene al menos una ciudad por cada una de las 24 jurisdicciones', function () {
    expect(Ciudad::distinct()->count('provincia'))->toBe(24);
});

it('todas las zonas horarias existen en la base IANA de PHP', function () {
    $validas = DateTimeZone::listIdentifiers();

    Ciudad::all()->each(fn (Ciudad $c) => expect($validas)->toContain($c->zona_horaria));
});

it('las coordenadas caen dentro de Argentina', function () {
    Ciudad::all()->each(fn (Ciudad $c) => expect($c->lat)->toBeBetween(-55.1, -21.7)
        ->and($c->lng)->toBeBetween(-73.6, -53.6));
});

it('es idempotente', function () {
    $antes = Ciudad::count();
    $this->seed(CiudadSeeder::class);

    expect(Ciudad::count())->toBe($antes);
});
