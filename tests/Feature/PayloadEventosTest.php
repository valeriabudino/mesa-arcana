<?php

use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\MazoMezclado;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\LecturaCarta;
use Database\Seeders\CartaSeeder;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Support\Facades\Event;

/*
| La regla de seguridad del trabajo: la carta viaja recién en CartaRevelada.
| Se testea sobre broadcastWith(), que es EXACTAMENTE lo que Laravel le manda a Reverb.
*/

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([MazoMezclado::class, CartaElegida::class, CartaRevelada::class]);

    $this->consulta = Consulta::factory()->create();
    $this->lectura = Lectura::factory()->for($this->consulta)->create();
    $this->actingAs($this->consulta->tarotista)->postJson(rutaLectura('mezclar', $this->lectura));
    $this->actingAs($this->consulta->consultante)->postJson(rutaLectura('elegir', $this->lectura), ['indice_abanico' => 5]);
    $this->elegida = $this->lectura->cartas()->with('carta')->sole();
});

it('CartaElegida NO filtra la carta: solo lectura, posicion, indice_abanico y a qué carta aclara', function () {
    Event::assertDispatched(CartaElegida::class, function (CartaElegida $evento) {
        $payload = $evento->broadcastWith();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        expect($payload)->toBe(['lectura' => $this->lectura->id, 'posicion' => 0, 'indice_abanico' => 5, 'aclara' => null])  // ni una clave más
            ->and($json)->not->toContain($this->elegida->carta->nombre)
            ->and($json)->not->toContain('carta')
            ->and($json)->not->toContain('invertida');

        return true;
    });
});

it('la sala tampoco muestra la carta elegida antes de revelarla', function () {
    $this->actingAs($this->consulta->consultante)->get(route('consultas.show', $this->consulta))
        ->assertOk()
        ->assertDontSee($this->elegida->carta->nombre)
        ->assertDontSee('orden_mazo');
});

it('MazoMezclado no manda el orden real, solo una semilla visual', function () {
    Event::assertDispatched(MazoMezclado::class, function (MazoMezclado $evento) {
        expect(array_keys($evento->broadcastWith()))->toBe(['lectura', 'semilla_animacion'])
            ->and($evento->broadcastWith()['semilla_animacion'])->toBeInt()
            // entra en 31 bits: JS la lee sin perder precisión y alimenta su generador de 32 bits
            ->and($evento->broadcastWith()['semilla_animacion'])->toBeBetween(1, 2_147_483_647);

        return true;
    });
});

it('CartaRevelada sí lleva la carta, con el significado según cómo salió', function () {
    $this->lectura->forceFill(['estado' => 'revelando'])->save();
    $this->actingAs($this->consulta->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 0])->assertNoContent();

    $carta = $this->elegida->carta;
    $esperado = $this->elegida->invertida ? $carta->significado_invertido : $carta->significado_derecho;

    Event::assertDispatched(CartaRevelada::class, function (CartaRevelada $evento) use ($carta, $esperado) {
        $payload = $evento->broadcastWith();

        expect($payload['carta']['nombre'])->toBe($carta->nombre)
            ->and($payload['carta']['significado'])->toBe($esperado)
            ->and($payload['carta']['imagen'])->toEndWith($carta->imagen)
            ->and($payload['invertida'])->toBe($this->elegida->invertida)
            ->and($payload['nombre_posicion'])->toBe('Pasado')
            ->and($payload['lectura'])->toBe($this->lectura->id);

        return true;
    });
});

it('todos los eventos van por el canal de presencia de la consulta con su broadcastAs', function (string $clase, array $args) {
    $evento = new $clase($this->consulta->uuid, ...$args);
    $canal = $evento->broadcastOn()[0];

    expect($canal)->toBeInstanceOf(PresenceChannel::class)
        ->and($canal->name)->toBe('presence-consulta.'.$this->consulta->uuid)
        ->and($evento->broadcastAs())->toBe(class_basename($clase));
})->with([
    'MazoMezclado' => [MazoMezclado::class, [1, 123]],
    'CartaElegida' => [CartaElegida::class, fn () => [new LecturaCarta(['posicion' => 0, 'indice_abanico' => 5])]],
    'CartaRevelada' => [CartaRevelada::class, fn () => [new LecturaCarta(['posicion' => 0, 'indice_abanico' => 5])]],
]);

it('la bitácora del chat tampoco nombra la carta elegida', function () {
    $bitacora = $this->consulta->mensajes()->pluck('contenido')->implode(' | ');

    expect($bitacora)->toContain('eligió su primera carta')
        ->and($bitacora)->not->toContain($this->elegida->carta->nombre);
});
