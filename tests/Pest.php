<?php

use App\Models\Lectura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Los tests de Feature escritos con Pest extienden TestCase (tienen la app
| de Laravel arrancada) y usan RefreshDatabase sobre la base "testing".
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| Helpers de las pruebas de Mesa Arcana.
*/

/** URL de una acción de lectura: /c/{consulta}/lecturas/{lectura}/{accion}. */
function rutaLectura(string $accion, Lectura $lectura): string
{
    return route('consultas.lecturas.'.$accion, [$lectura->consulta, $lectura]);
}
