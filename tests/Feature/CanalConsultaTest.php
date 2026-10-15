<?php

use App\Models\Consulta;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| En los tests BROADCAST_CONNECTION=null, y ese driver autoriza cualquier canal sin mirar
| routes/channels.php. Para probar la regla de verdad usamos el driver reverb con
| credenciales de mentira: la firma del permiso es local, no hace falta Reverb corriendo.
*/
beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'clave-test',
        'broadcasting.connections.reverb.secret' => 'secreto-test',
        'broadcasting.connections.reverb.app_id' => '1',
    ]);
    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');

    $this->consulta = Consulta::factory()->create();
    $this->consulta->consultante->update(['name' => 'María']);
});

function pedirCanal(Consulta $consulta)
{
    return test()->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'presence-consulta.'.$consulta->uuid,
    ]);
}

it('deja entrar a la consultante y comparte solo id, nombre y rol', function () {
    $consultante = $this->consulta->consultante;
    $this->actingAs($consultante);
    $respuesta = pedirCanal($this->consulta)->assertOk();

    $info = json_decode($respuesta->json('channel_data'), true)['user_info'];
    expect($info)->toBe(['id' => $consultante->id, 'name' => 'María', 'rol' => 'consultante']);
});

it('no comparte el email aunque la persona tenga uno', function () {
    $this->actingAs($this->consulta->tarotista);

    expect(pedirCanal($this->consulta)->assertOk()->json('channel_data'))->not->toContain($this->consulta->tarotista->email);
});

it('rechaza a quien no es de la consulta', function (string $rol) {
    $this->actingAs(User::factory()->{$rol}()->create());
    pedirCanal($this->consulta)->assertForbidden();
})->with(['tarotista', 'consultante']);

it('rechaza a una visitante sin login', function () {
    pedirCanal($this->consulta)->assertForbidden();
});
