<?php

use App\Enums\EstadoSesion as E;

// Las únicas 4 transiciones válidas de la máquina de estados.
$validas = [
    [E::Esperando, E::Lista],
    [E::Lista, E::Eligiendo],
    [E::Eligiendo, E::Revelando],
    [E::Revelando, E::Finalizada],
];

// Todas las combinaciones actual × nuevo (25 casos).
$combinaciones = [];
foreach (E::cases() as $actual) {
    foreach (E::cases() as $nuevo) {
        $combinaciones["{$actual->value} → {$nuevo->value}"] = [$actual, $nuevo];
    }
}

it('solo permite las transiciones del diagrama', function (E $actual, E $nuevo) use ($validas) {
    expect($actual->puedePasarA($nuevo))->toBe(in_array([$actual, $nuevo], $validas, true));
})->with($combinaciones);

it('no sale de Finalizada', function () {
    expect(E::Finalizada->siguientes())->toBe([]);
});
