<?php

use App\Enums\TipoTirada;

it('sabe cuántas cartas lleva cada tirada', function (TipoTirada $tipo, int $cantidad) {
    expect($tipo->cantidad())->toBe($cantidad)
        ->and($tipo->posiciones())->toHaveCount($cantidad);
})->with([
    [TipoTirada::TresCartas, 3],
    [TipoTirada::SiNo, 1],
    [TipoTirada::CruzCelta, 10],
]);

it('nombra las posiciones de tres cartas', function () {
    expect(TipoTirada::TresCartas->nombrePosicion(1))->toBe('Presente');
});
