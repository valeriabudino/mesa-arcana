<?php

use App\Enums\EstadoSesion;
use App\Models\Carta;
use App\Models\Sesion;
use App\Models\SesionCarta;
use App\Models\User;
use Database\Seeders\CartaSeeder;
use Illuminate\Database\QueryException;

it('genera un uuid al crear y lo usa como clave de ruta', function () {
    $sesion = Sesion::factory()->create();

    expect($sesion->uuid)->toBeString()->toHaveLength(36)
        ->and($sesion->getRouteKey())->toBe($sesion->uuid)
        ->and($sesion->estado)->toBe(EstadoSesion::Esperando);
});

it('nunca serializa orden_mazo', function () {
    $sesion = Sesion::factory()->create();
    $sesion->orden_mazo = range(1, 78);
    $sesion->save();

    expect($sesion->fresh()->orden_mazo)->toHaveCount(78)              // está guardado…
        ->and($sesion->fresh()->toArray())->not->toHaveKey('orden_mazo') // …pero no sale
        ->and($sesion->fresh()->toJson())->not->toContain('orden_mazo');
});

it('no serializa qué carta es ni si está invertida', function () {
    $this->seed(CartaSeeder::class);
    $sesion = Sesion::factory()->conConsultante()->create();
    $elegida = crearSesionCarta($sesion, posicion: 0, indice: 5);

    expect($elegida->load('carta')->toArray())
        ->not->toHaveKeys(['carta_id', 'invertida', 'carta'])
        ->toHaveKeys(['posicion', 'indice_abanico']);
});

it('sabe quién participa de la sesión', function () {
    $consultante = User::factory()->consultante()->create();
    $sesion = Sesion::factory()->conConsultante($consultante)->create();
    $otra = User::factory()->consultante()->create();

    expect($sesion->participa($sesion->tarotista))->toBeTrue()
        ->and($sesion->participa($consultante))->toBeTrue()
        ->and($sesion->participa($otra))->toBeFalse();
});

it('la base rechaza dos cartas en la misma posición', function () {
    $this->seed(CartaSeeder::class);
    $sesion = Sesion::factory()->conConsultante()->create();
    crearSesionCarta($sesion, posicion: 0, indice: 5);

    crearSesionCarta($sesion, posicion: 0, indice: 9);
})->throws(QueryException::class);

it('la base rechaza elegir dos veces la misma carta del abanico', function () {
    $this->seed(CartaSeeder::class);
    $sesion = Sesion::factory()->conConsultante()->create();
    crearSesionCarta($sesion, posicion: 0, indice: 5);

    crearSesionCarta($sesion, posicion: 1, indice: 5);
})->throws(QueryException::class);

function crearSesionCarta(Sesion $sesion, int $posicion, int $indice): SesionCarta
{
    $sc = new SesionCarta(['posicion' => $posicion, 'indice_abanico' => $indice]);
    $sc->sesion_id = $sesion->id;
    $sc->carta_id = Carta::inRandomOrder()->value('id');
    $sc->invertida = false;
    $sc->save();

    return $sc;
}
