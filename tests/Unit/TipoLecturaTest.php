<?php

use App\Enums\EstadoLectura;
use App\Enums\TipoLectura;

it('sabe cuántas cartas lleva cada tirada', function (TipoLectura $tipo, int $cantidad) {
    expect($tipo->cantidad())->toBe($cantidad)
        ->and($tipo->posiciones())->toHaveCount($cantidad);
})->with([
    [TipoLectura::TresCartas, 3],
    [TipoLectura::SiNo, 1],
    [TipoLectura::CruzCelta, 10],
    [TipoLectura::CartaAstral, 0],
]);

it('nombra las posiciones de tres cartas', function () {
    expect(TipoLectura::TresCartas->nombrePosicion(1))->toBe('Presente');
});

it('las tiradas arrancan en Lista y la carta astral esperando los datos', function () {
    expect(TipoLectura::TresCartas->estadoInicial())->toBe(EstadoLectura::Lista)
        ->and(TipoLectura::CartaAstral->estadoInicial())->toBe(EstadoLectura::EsperandoDatos)
        ->and(TipoLectura::tiradas())->not->toContain(TipoLectura::CartaAstral)->toHaveCount(3);
});
