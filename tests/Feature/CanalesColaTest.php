<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Ver CanalConsultaTest: con el driver null cualquier canal se autoriza; usamos reverb con credenciales de mentira.
beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'clave-test',
        'broadcasting.connections.reverb.secret' => 'secreto-test',
        'broadcasting.connections.reverb.app_id' => '1',
    ]);
    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');
});

function pedirCanalPrivado(string $canal)
{
    return test()->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-'.$canal]);
}

it('el canal tarotistas es para cualquier tarotista, esté disponible o no', function (bool $disponible) {
    $this->actingAs(User::factory()->tarotista()->create(['disponible' => $disponible]));
    pedirCanalPrivado('tarotistas')->assertOk();
})->with([true, false]);

it('una consultante no escucha el canal tarotistas', function () {
    $this->actingAs(User::factory()->invitada()->create());
    pedirCanalPrivado('tarotistas')->assertForbidden();
});

it('cada una escucha solo su propio canal privado', function () {
    $maria = User::factory()->invitada()->create();
    $otra = User::factory()->invitada()->create();
    $this->actingAs($maria);

    pedirCanalPrivado('App.Models.User.'.$maria->id)->assertOk();
    pedirCanalPrivado('App.Models.User.'.$otra->id)->assertForbidden();
});
