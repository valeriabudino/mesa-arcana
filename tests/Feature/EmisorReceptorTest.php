<?php

use App\Events\RemoteStyleUpdate;
use Illuminate\Support\Facades\Event;

it('emite RemoteStyleUpdate al enviar el formulario del emisor', function () {
    Event::fake([RemoteStyleUpdate::class]);

    $this->post(route('emitir'), ['newText' => 'Hola Reverb', 'newStyle' => 'color:red'])
        ->assertRedirect();

    Event::assertDispatched(RemoteStyleUpdate::class, fn ($e) => $e->newText === 'Hola Reverb');
});

it('viaja por el canal público style-channel con el nombre que escucha Echo', function () {
    $evento = new RemoteStyleUpdate('Hola', '');

    expect($evento->broadcastOn()[0]->name)->toBe('style-channel')
        ->and($evento->broadcastAs())->toBe('RemoteStyleUpdate');
});

it('exige el texto', function () {
    $this->post(route('emitir'), ['newText' => ''])
        ->assertSessionHasErrors('newText');
});
