<?php

use App\Enums\Arcano;
use App\Models\Carta;
use Database\Seeders\CartaSeeder;

it('carga las 78 cartas: 22 mayores y 14 por palo', function () {
    $this->seed(CartaSeeder::class);

    expect(Carta::count())->toBe(78)
        ->and(Carta::where('arcano', Arcano::Mayor)->count())->toBe(22)
        ->and(Carta::whereNotNull('palo')->groupBy('palo')->selectRaw('count(*) as c')->pluck('c')->unique()->all())->toBe([14]);
});

it('cada carta tiene su imagen en public/cartas', function () {
    $this->seed(CartaSeeder::class);

    Carta::all()->each(fn (Carta $carta) => expect(public_path($carta->imagen))->toBeFile());
});

it('es idempotente', function () {
    $this->seed(CartaSeeder::class);
    $this->seed(CartaSeeder::class);

    expect(Carta::count())->toBe(78);
});
