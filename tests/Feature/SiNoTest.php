<?php

use App\Enums\SiNo;
use App\Enums\TipoLectura;
use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Models\Carta;
use App\Models\Consulta;
use App\Models\Lectura;
use Database\Seeders\CartaSeeder;
use Illuminate\Support\Facades\Event;

it('la regla: al derecho vale la carta; invertida se debilita un paso', function (SiNo $carta, bool $invertida, SiNo $respuesta) {
    expect($carta->segun($invertida))->toBe($respuesta);
})->with([
    [SiNo::Si, false, SiNo::Si],
    [SiNo::Si, true, SiNo::Quizas],
    [SiNo::Quizas, false, SiNo::Quizas],
    [SiNo::Quizas, true, SiNo::No],
    [SiNo::No, false, SiNo::No],
    [SiNo::No, true, SiNo::No],
]);

it('la respuesta viaja recién con CartaRevelada (calculada en el servidor)', function () {
    $this->seed(CartaSeeder::class);
    Event::fake([CartaElegida::class, CartaRevelada::class]);
    $consulta = Consulta::factory()->create();
    $lectura = Lectura::factory()->for($consulta)->tipo(TipoLectura::SiNo)->create();

    $this->actingAs($consulta->tarotista)->postJson(rutaLectura('mezclar', $lectura));
    $this->actingAs($consulta->consultante)->postJson(rutaLectura('elegir', $lectura), ['indice_abanico' => 10]);
    Event::assertDispatched(CartaElegida::class, fn ($e) => ! array_key_exists('respuesta', $e->broadcastWith()));

    $this->actingAs($consulta->tarotista)->postJson(rutaLectura('revelar', $lectura), ['posicion' => 0]);

    $elegida = $lectura->cartas()->sole();
    $esperada = Carta::find($elegida->carta_id)->si_no->segun($elegida->invertida);
    Event::assertDispatched(CartaRevelada::class, fn ($e) => $e->broadcastWith()['respuesta'] === ['valor' => $esperada->value, 'texto' => $esperada->etiqueta()]);
});

it('en las tiradas comunes no hay respuesta de sí o no', function () {
    $this->seed(CartaSeeder::class);
    Event::fake([CartaRevelada::class]);
    $consulta = Consulta::factory()->create();
    $lectura = Lectura::factory()->for($consulta)->tipo(TipoLectura::TresCartas)->create();
    $this->actingAs($consulta->tarotista)->postJson(rutaLectura('mezclar', $lectura));
    foreach ([1, 2, 3] as $i) {
        $this->actingAs($consulta->consultante)->postJson(rutaLectura('elegir', $lectura), ['indice_abanico' => $i]);
    }
    $this->actingAs($consulta->tarotista)->postJson(rutaLectura('revelar', $lectura), ['posicion' => 0]);

    Event::assertDispatched(CartaRevelada::class, fn ($e) => ! array_key_exists('respuesta', $e->broadcastWith()));
});
