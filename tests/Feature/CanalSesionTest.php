<?php

use App\Models\Sesion;
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

    $this->consultante = User::factory()->consultante()->create(['name' => 'María']);
    $this->sesion = Sesion::factory()->conConsultante($this->consultante)->create();
});

function pedirCanal(Sesion $sesion)
{
    return test()->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'presence-sesion.'.$sesion->uuid,
    ]);
}

it('deja entrar a la consultante y comparte solo id, nombre y rol', function () {
    $this->actingAs($this->consultante);
    $respuesta = pedirCanal($this->sesion)->assertOk();

    $info = json_decode($respuesta->json('channel_data'), true)['user_info'];
    expect($info)->toBe(['id' => $this->consultante->id, 'name' => 'María', 'rol' => 'consultante'])
        ->and($respuesta->json('channel_data'))->not->toContain($this->consultante->email);
});

it('deja entrar a la tarotista dueña', function () {
    $this->actingAs($this->sesion->tarotista);
    pedirCanal($this->sesion)->assertOk();
});

it('rechaza a una consultante que no es de la sesión', function () {
    $this->actingAs(User::factory()->consultante()->create());
    pedirCanal($this->sesion)->assertForbidden();
});

it('rechaza a otra tarotista', function () {
    $this->actingAs(User::factory()->tarotista()->create());
    pedirCanal($this->sesion)->assertForbidden();
});

it('rechaza a una visitante sin login', function () {
    pedirCanal($this->sesion)->assertForbidden();
});
