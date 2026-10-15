<?php

use App\Enums\TipoTirada;
use App\Http\Controllers\LecturaController;
use App\Http\Controllers\RemoteStyleController;
use App\Http\Controllers\SesionController;
use App\Models\Carta;
use App\Models\Sesion;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Fase 0: prueba emisor → receptor de la guía (se quitan en la Fase 5)
Route::get('/emisor', [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir', [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');

// Fase 3: laboratorio de la escena 3D (animaciones con botones, sin sockets). Solo en local.
if (app()->isLocal()) {
    Route::get('/laboratorio-mesa', fn () => view('laboratorio-mesa', [
        'tiradas' => collect(TipoTirada::cases())->mapWithKeys(fn (TipoTirada $t) => [
            $t->value => ['nombre' => $t->nombre(), 'posiciones' => $t->posiciones()],
        ]),
        'cartas' => Carta::all()->map(fn (Carta $c) => [
            'nombre' => $c->nombre,
            'imagen' => $c->urlImagen(),
            'significado_derecho' => $c->significado_derecho,
            'significado_invertido' => $c->significado_invertido,
            'palabras_clave' => $c->palabras_clave,
        ]),
    ]))->name('laboratorio-mesa');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Lecturas: {sesion} se resuelve por uuid (Sesion::getRouteKeyName)

// La consultante abre el link y entra solo con su nombre (sin cuenta).
Route::get('s/{sesion}', [SesionController::class, 'show'])->name('sesiones.show')->can('entrar', 'sesion');
Route::post('s/{sesion}/ingresar', [SesionController::class, 'ingresar'])->name('sesiones.ingresar')
    ->can('ingresar', 'sesion')
    ->middleware('throttle:10,1'); // como mucho 10 intentos por minuto: nadie crea invitadas en masa

Route::middleware('auth')->group(function () {
    Route::post('sesiones', [SesionController::class, 'store'])->name('sesiones.store')->can('create', Sesion::class);

    Route::prefix('s/{sesion}')->name('sesiones.')->group(function () {
        Route::get('resumen', [SesionController::class, 'resumen'])->name('resumen')->can('verResumen', 'sesion');

        Route::post('mezclar', [LecturaController::class, 'mezclar'])->name('mezclar')->can('mezclar', 'sesion');
        Route::post('elegir', [LecturaController::class, 'elegir'])->name('elegir')->can('elegir', 'sesion');
        Route::post('revelar', [LecturaController::class, 'revelar'])->name('revelar')->can('revelar', 'sesion');
        Route::post('finalizar', [LecturaController::class, 'finalizar'])->name('finalizar')->can('finalizar', 'sesion');
    });
});

require __DIR__.'/settings.php';
