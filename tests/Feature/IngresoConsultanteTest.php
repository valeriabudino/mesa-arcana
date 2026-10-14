<?php

use App\Enums\EstadoSesion;
use App\Enums\Rol;
use App\Events\ConsultanteIngreso;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([ConsultanteIngreso::class]);
    $this->sesion = Sesion::factory()->create();
});

it('ya no existe el registro público', function () {
    $this->get('/register')->assertNotFound();
    $this->get(route('login'))->assertOk()->assertDontSee('Sign up');
});

it('la visitante ve el formulario con el nombre de la tarotista', function () {
    $this->get(route('sesiones.show', $this->sesion))
        ->assertOk()
        ->assertSee('¿Cómo te llamás?')
        ->assertSee($this->sesion->tarotista->name);
});

it('entra solo con su nombre: queda como consultante invitada, logueada y asociada', function () {
    $this->post(route('sesiones.ingresar', $this->sesion), ['nombre' => 'María'])
        ->assertRedirect(route('sesiones.show', $this->sesion));

    $maria = User::firstWhere('name', 'María');
    expect($maria->rol)->toBe(Rol::Consultante)
        ->and($maria->email)->toBeNull()
        ->and($maria->password)->toBeNull()
        ->and($maria->esInvitada())->toBeTrue()
        ->and($this->sesion->fresh()->consultante_id)->toBe($maria->id)
        ->and($this->sesion->fresh()->estado)->toBe(EstadoSesion::Lista);
    $this->assertAuthenticatedAs($maria);

    Event::assertDispatched(ConsultanteIngreso::class, fn ($e) => $e->broadcastWith() === ['nombre' => 'María']);

    // Al recargar ya está adentro: ve la mesa, no el formulario
    $this->get(route('sesiones.show', $this->sesion))->assertOk()->assertSee('Lista para mezclar');
});

it('una segunda visitante no puede entrar a una sesión ocupada ni crear una invitada', function () {
    $this->post(route('sesiones.ingresar', $this->sesion), ['nombre' => 'María']);
    auth()->logout();
    $usuarios = User::count();

    $this->get(route('sesiones.show', $this->sesion))->assertForbidden();
    $this->post(route('sesiones.ingresar', $this->sesion), ['nombre' => 'Intrusa'])->assertForbidden();

    expect(User::count())->toBe($usuarios);
});

it('exige un nombre de hasta 40 caracteres', function (string $nombre) {
    $this->post(route('sesiones.ingresar', $this->sesion), ['nombre' => $nombre])->assertSessionHasErrors('nombre');
    expect($this->sesion->fresh()->consultante_id)->toBeNull();
})->with(['vacío' => '', 'muy largo' => str_repeat('a', 41)]);

it('con login no se usa el formulario del nombre', function () {
    $this->actingAs(User::factory()->tarotista()->create())
        ->post(route('sesiones.ingresar', $this->sesion), ['nombre' => 'X'])
        ->assertForbidden();
});

it('limita los intentos de ingreso por minuto', function () {
    foreach (range(1, 10) as $i) {
        $this->post(route('sesiones.ingresar', $this->sesion), ['nombre' => '']);
    }

    $this->post(route('sesiones.ingresar', $this->sesion), ['nombre' => ''])->assertTooManyRequests();
});
