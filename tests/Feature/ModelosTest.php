<?php

use App\Enums\EstadoLectura;
use App\Enums\TipoLectura;
use App\Exceptions\AccionFueraDeOrden;
use App\Models\Carta;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\LecturaCarta;
use App\Models\Turno;
use App\Models\User;
use Database\Seeders\CartaSeeder;
use Illuminate\Database\QueryException;

it('turnos y consultas usan un uuid como clave pública', function () {
    foreach ([Turno::factory()->create(), Consulta::factory()->create()] as $registro) {
        expect($registro->uuid)->toBeString()->toHaveLength(36)
            ->and($registro->getRouteKey())->toBe($registro->uuid);
    }
});

it('cada lectura arranca en el estado de su tipo', function () {
    expect(Lectura::factory()->create()->estado)->toBe(EstadoLectura::Lista)
        ->and(Lectura::factory()->tipo(TipoLectura::CartaAstral)->create()->estado)->toBe(EstadoLectura::EsperandoDatos);
});

it('nunca serializa orden_mazo', function () {
    $lectura = Lectura::factory()->create();
    $lectura->orden_mazo = range(1, 78);
    $lectura->save();

    expect($lectura->fresh()->orden_mazo)->toHaveCount(78)              // está guardado…
        ->and($lectura->fresh()->toArray())->not->toHaveKey('orden_mazo') // …pero no sale
        ->and($lectura->fresh()->toJson())->not->toContain('orden_mazo');
});

it('no serializa qué carta es ni si está invertida', function () {
    $this->seed(CartaSeeder::class);
    $elegida = crearLecturaCarta(Lectura::factory()->create(), posicion: 0, indice: 5);

    expect($elegida->load('carta')->toArray())
        ->not->toHaveKeys(['carta_id', 'invertida', 'carta'])
        ->toHaveKeys(['posicion', 'indice_abanico']);
});

it('sabe quién participa de la consulta', function () {
    $consulta = Consulta::factory()->create();

    expect($consulta->participa($consulta->tarotista))->toBeTrue()
        ->and($consulta->participa($consulta->consultante))->toBeTrue()
        ->and($consulta->participa(User::factory()->consultante()->create()))->toBeFalse()
        ->and($consulta->participa(User::factory()->tarotista()->create()))->toBeFalse();
});

it('la consulta nace de un turno tomado por su misma tarotista', function () {
    $consulta = Consulta::factory()->create();

    expect($consulta->turno->tarotista_id)->toBe($consulta->tarotista_id)
        ->and($consulta->turno->consultante_id)->toBe($consulta->consultante_id);
});

it('la lectura activa es la última que no está cerrada', function () {
    $consulta = Consulta::factory()->create();
    expect($consulta->lecturaActiva)->toBeNull();

    Lectura::factory()->for($consulta)->create(['estado' => EstadoLectura::Cerrada]);
    $abierta = Lectura::factory()->for($consulta)->create();

    expect($consulta->fresh()->lecturaActiva->id)->toBe($abierta->id);
});

it('pasarA valida contra la máquina de estados', function () {
    $lectura = Lectura::factory()->create();
    $lectura->pasarA(EstadoLectura::Eligiendo);
    expect($lectura->estado)->toBe(EstadoLectura::Eligiendo);

    $lectura->pasarA(EstadoLectura::Abierta); // sin pasar por Revelando: no se puede
})->throws(AccionFueraDeOrden::class);

it('la base rechaza dos cartas en la misma posición', function () {
    $this->seed(CartaSeeder::class);
    $lectura = Lectura::factory()->create();
    crearLecturaCarta($lectura, posicion: 0, indice: 5);

    crearLecturaCarta($lectura, posicion: 0, indice: 9);
})->throws(QueryException::class);

it('la base rechaza elegir dos veces la misma carta del abanico', function () {
    $this->seed(CartaSeeder::class);
    $lectura = Lectura::factory()->create();
    crearLecturaCarta($lectura, posicion: 0, indice: 5);

    crearLecturaCarta($lectura, posicion: 1, indice: 5);
})->throws(QueryException::class);

it('la base no deja que un turno tenga dos consultas', function () {
    $consulta = Consulta::factory()->create();

    Consulta::factory()->create(['turno_id' => $consulta->turno_id]);
})->throws(QueryException::class);

function crearLecturaCarta(Lectura $lectura, int $posicion, int $indice): LecturaCarta
{
    $lc = new LecturaCarta(['posicion' => $posicion, 'indice_abanico' => $indice]);
    $lc->lectura_id = $lectura->id;
    $lc->carta_id = Carta::inRandomOrder()->value('id');
    $lc->invertida = false;
    $lc->save();

    return $lc;
}
