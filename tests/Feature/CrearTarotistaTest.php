<?php

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('crea una tarotista desde la consola', function () {
    $this->artisan('tarotista:crear', ['--name' => 'Valeria', '--email' => 'vale@mesa.test', '--password' => 'secreta123'])
        ->assertSuccessful();

    $vale = User::firstWhere('email', 'vale@mesa.test');
    expect($vale->rol)->toBe(Rol::Tarotista)
        ->and(Hash::check('secreta123', $vale->password))->toBeTrue();
});

it('no repite emails ni acepta contraseñas cortas', function () {
    User::factory()->tarotista()->create(['email' => 'vale@mesa.test']);

    $this->artisan('tarotista:crear', ['--name' => 'Otra', '--email' => 'vale@mesa.test', '--password' => 'secreta123'])->assertFailed();
    $this->artisan('tarotista:crear', ['--name' => 'Otra', '--email' => 'otra@mesa.test', '--password' => 'corta'])->assertFailed();

    expect(User::count())->toBe(1);
});
