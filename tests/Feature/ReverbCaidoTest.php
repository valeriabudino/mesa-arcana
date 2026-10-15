<?php

use App\Enums\EstadoLectura;
use App\Models\Consulta;
use App\Models\Lectura;
use Database\Seeders\CartaSeeder;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Exceptions;

/*
| Qué pasa si Reverb no está corriendo: la acción ya quedó guardada (el evento se
| emite después del commit), así que NO se le responde a la persona como si hubiera
| fallado. El error queda en el log y las pantallas se ponen al día al reconectarse.
*/
beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'clave-test',
        'broadcasting.connections.reverb.secret' => 'secreto-test',
        'broadcasting.connections.reverb.app_id' => '1',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 1, // ahí no escucha nadie
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);
    Broadcast::forgetDrivers();
    $this->seed(CartaSeeder::class);
});

it('con Reverb caído, mezclar igual responde 204, guarda y deja el error en el log', function () {
    Exceptions::fake();
    $lectura = Lectura::factory()->for(Consulta::factory())->create();

    $this->actingAs($lectura->consulta->tarotista)->postJson(rutaLectura('mezclar', $lectura))->assertNoContent();

    expect($lectura->fresh()->estado)->toBe(EstadoLectura::Eligiendo)
        ->and($lectura->fresh()->orden_mazo)->toHaveCount(78);
    Exceptions::assertReported(BroadcastException::class);
});
