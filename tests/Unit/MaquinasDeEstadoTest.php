<?php

use App\Enums\EstadoConsulta;
use App\Enums\EstadoLectura as L;
use App\Enums\EstadoTurno as T;

/*
| Las tres máquinas de estado del plan (§6), probadas con TODAS las combinaciones
| actual × nuevo: solo pasan las transiciones del diagrama.
*/

function todasLasCombinaciones(string $enum): array
{
    $combinaciones = [];
    foreach ($enum::cases() as $actual) {
        foreach ($enum::cases() as $nuevo) {
            $combinaciones["{$actual->value} → {$nuevo->value}"] = [$actual, $nuevo];
        }
    }

    return $combinaciones;
}

it('Lectura: solo las transiciones del diagrama', function (L $actual, L $nuevo) {
    $validas = [
        [L::Lista, L::Eligiendo],
        [L::Eligiendo, L::Revelando],
        [L::Revelando, L::Abierta],
        [L::Abierta, L::Eligiendo], // carta aclaratoria
        [L::Abierta, L::Cerrada],
        [L::EsperandoDatos, L::Calculada],
        [L::Calculada, L::Cerrada],
    ];

    expect($actual->puedePasarA($nuevo))->toBe(in_array([$actual, $nuevo], $validas, true));
})->with(todasLasCombinaciones(L::class));

it('Turno: solo las transiciones del diagrama', function (T $actual, T $nuevo) {
    $validas = [
        [T::Esperando, T::Tomado],
        [T::Esperando, T::Cancelado],
        [T::Tomado, T::Ausente],
    ];

    expect($actual->puedePasarA($nuevo))->toBe(in_array([$actual, $nuevo], $validas, true));
})->with(todasLasCombinaciones(T::class));

it('Consulta: de Activa a Finalizada y de ahí no sale', function () {
    expect(EstadoConsulta::Activa->puedePasarA(EstadoConsulta::Finalizada))->toBeTrue()
        ->and(EstadoConsulta::Finalizada->puedePasarA(EstadoConsulta::Activa))->toBeFalse()
        ->and(EstadoConsulta::Finalizada->esFinal())->toBeTrue();
});

it('los estados finales no tienen salida', function () {
    expect(L::Cerrada->esFinal())->toBeTrue()
        ->and(T::Cancelado->esFinal())->toBeTrue()
        ->and(T::Ausente->esFinal())->toBeTrue()
        ->and(L::Abierta->esFinal())->toBeFalse();
});
