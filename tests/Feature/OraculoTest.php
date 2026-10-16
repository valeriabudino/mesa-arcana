<?php

use App\Events\OraculoRevelado;
use App\Models\Oraculo;
use App\Services\OraculoService;
use Database\Seeders\CartaSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([OraculoRevelado::class]);
});

afterEach(fn () => Carbon::setTestNow());

it('antes de revelarse la carta no existe: el inicio muestra la cuenta regresiva', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-16 08:30', OraculoService::ZONA));

    $this->get('/')
        ->assertOk()
        ->assertSee('Oráculo del día')
        ->assertSee('"revelado":null', false)
        ->assertSee('"proxima":"2026-10-16T09:00:00-03:00"', false);
    expect(Oraculo::count())->toBe(0);
});

it('revelar elige la carta, la guarda con el día argentino y avisa por el canal público', function () {
    // 01:30 UTC del 16 es todavía el 15 en Argentina.
    Carbon::setTestNow(Carbon::parse('2026-10-16 01:30', 'UTC'));

    $this->artisan('oraculo:revelar')->assertSuccessful();

    $oraculo = Oraculo::with('carta')->sole();
    expect($oraculo->fecha->toDateString())->toBe('2026-10-15')
        ->and($oraculo->mensaje)->toBe($oraculo->invertida ? $oraculo->carta->significado_invertido : $oraculo->carta->significado_derecho);
    Event::assertDispatched(OraculoRevelado::class, fn (OraculoRevelado $e) => $e->broadcastOn()[0]->name === 'oraculo'
        && $e->broadcastWith() === $oraculo->datosPublicos());
});

it('una vez por día: correrlo de nuevo no cambia la carta, salvo con --otra', function () {
    $this->artisan('oraculo:revelar');
    $primera = Oraculo::sole()->only(['carta_id', 'invertida']);

    $this->artisan('oraculo:revelar')->expectsOutputToContain('ya estaba revelado');
    expect(Oraculo::sole()->only(['carta_id', 'invertida']))->toBe($primera);
    Event::assertDispatchedTimes(OraculoRevelado::class, 1);

    $this->artisan('oraculo:revelar --otra')->assertSuccessful();
    expect(Oraculo::count())->toBe(1);
    Event::assertDispatchedTimes(OraculoRevelado::class, 2);
});

it('ya revelado, el inicio lo muestra (sin login)', function () {
    $this->artisan('oraculo:revelar');
    $oraculo = Oraculo::with('carta')->sole();

    $this->get('/')->assertOk()->assertSee($oraculo->carta->nombre)->assertSee($oraculo->mensaje);
});

it('después de las 9 sin revelar, la próxima es mañana a las 9', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-16 10:00', OraculoService::ZONA));

    expect(app(OraculoService::class)->proximaRevelacion()->toIso8601String())->toBe('2026-10-17T09:00:00-03:00');
});

it('el scheduler lo revela todos los días a las 9 de Argentina', function () {
    $evento = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'oraculo:revelar'));

    expect($evento)->not->toBeNull()
        ->and($evento->expression)->toBe('0 9 * * *')
        ->and($evento->timezone)->toBe(OraculoService::ZONA);
});

it('si pasaron las 9 y nadie lo reveló (sin scheduler), el inicio lo revela', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-16 09:05', OraculoService::ZONA));

    $this->get('/')->assertOk()->assertDontSee('"revelado":null', false);

    expect(Oraculo::sole()->fecha->toDateString())->toBe('2026-10-16');
    Event::assertDispatchedTimes(OraculoRevelado::class, 1);
    $this->get('/'); // la segunda visita no sortea otra
    Event::assertDispatchedTimes(OraculoRevelado::class, 1);
});

it('la imagen viaja como ruta relativa: sirve desde cualquier dominio aunque lo revele un comando', function () {
    $this->artisan('oraculo:revelar');

    expect(Oraculo::with('carta')->sole()->datosPublicos()['carta']['imagen'])->toStartWith('/cartas/')->not->toContain('http');
});
