<?php

use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\ConsultanteIngreso;
use App\Events\MazoMezclado;
use App\Events\SesionFinalizada;
use App\Models\Sesion;
use App\Models\User;
use Database\Seeders\CartaSeeder;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Support\Facades\Event;

/*
| La regla de seguridad del trabajo: la carta viaja recién en CartaRevelada.
| Se testea sobre broadcastWith(), que es EXACTAMENTE lo que Laravel le manda a Reverb.
*/

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([ConsultanteIngreso::class, MazoMezclado::class, CartaElegida::class, CartaRevelada::class, SesionFinalizada::class]);

    $this->consultante = User::factory()->consultante()->create();
    $this->sesion = Sesion::factory()->conConsultante($this->consultante)->create();
    $this->actingAs($this->sesion->tarotista)->postJson(route('sesiones.mezclar', $this->sesion));
    $this->actingAs($this->consultante)->postJson(route('sesiones.elegir', $this->sesion), ['indice_abanico' => 5]);
    $this->elegida = $this->sesion->cartas()->with('carta')->sole();
});

it('CartaElegida NO filtra la carta: solo posicion e indice_abanico', function () {
    Event::assertDispatched(CartaElegida::class, function (CartaElegida $evento) {
        $payload = $evento->broadcastWith();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        expect($payload)->toBe(['posicion' => 0, 'indice_abanico' => 5])  // ni una clave más
            ->and($json)->not->toContain($this->elegida->carta->nombre)
            ->and($json)->not->toContain('carta')
            ->and($json)->not->toContain('invertida');

        return true;
    });
});

it('la mesa tampoco muestra la carta elegida antes de revelarla', function () {
    $this->actingAs($this->consultante)->get(route('sesiones.show', $this->sesion))
        ->assertOk()
        ->assertDontSee($this->elegida->carta->nombre)
        ->assertDontSee('orden_mazo');
});

it('MazoMezclado no manda el orden real, solo una semilla visual', function () {
    Event::assertDispatched(MazoMezclado::class, function (MazoMezclado $evento) {
        expect(array_keys($evento->broadcastWith()))->toBe(['semilla_animacion'])
            ->and($evento->broadcastWith()['semilla_animacion'])->toBeInt()
            // entra en 31 bits: JS la lee sin perder precisión y alimenta su generador de 32 bits
            ->and($evento->broadcastWith()['semilla_animacion'])->toBeBetween(1, 2_147_483_647);

        return true;
    });
});

it('CartaRevelada sí lleva la carta, con el significado según cómo salió', function () {
    $this->sesion->forceFill(['estado' => 'revelando'])->save();
    $this->actingAs($this->sesion->tarotista)->postJson(route('sesiones.revelar', $this->sesion), ['posicion' => 0])->assertNoContent();

    $carta = $this->elegida->carta;
    $esperado = $this->elegida->invertida ? $carta->significado_invertido : $carta->significado_derecho;

    Event::assertDispatched(CartaRevelada::class, function (CartaRevelada $evento) use ($carta, $esperado) {
        $payload = $evento->broadcastWith();

        expect($payload['carta']['nombre'])->toBe($carta->nombre)
            ->and($payload['carta']['significado'])->toBe($esperado)
            ->and($payload['carta']['imagen'])->toEndWith($carta->imagen)
            ->and($payload['invertida'])->toBe($this->elegida->invertida)
            ->and($payload['nombre_posicion'])->toBe('Pasado');

        return true;
    });
});

it('todos los eventos van por el canal de presencia de la sesión con su broadcastAs', function (string $clase, array $args) {
    $evento = new $clase($this->sesion->uuid, ...$args);
    $canal = $evento->broadcastOn()[0];

    expect($canal)->toBeInstanceOf(PresenceChannel::class)
        ->and($canal->name)->toBe('presence-sesion.'.$this->sesion->uuid)
        ->and($evento->broadcastAs())->toBe(class_basename($clase));
})->with([
    'ConsultanteIngreso' => [ConsultanteIngreso::class, ['María']],
    'MazoMezclado' => [MazoMezclado::class, [123]],
    'CartaElegida' => [CartaElegida::class, [0, 5]],
    'SesionFinalizada' => [SesionFinalizada::class, ['http://localhost/s/x/resumen']],
]);
