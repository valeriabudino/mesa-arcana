<?php

use App\Enums\TipoLectura;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\LecturaController;
use App\Http\Controllers\RemoteStyleController;
use App\Models\Carta;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Fase 0: prueba emisor → receptor de la guía (se quitan en la Fase 5)
Route::get('/emisor', [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir', [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');

// Fase 3: laboratorio de la escena 3D (animaciones con botones, sin sockets). Solo en local.
if (app()->isLocal()) {
    Route::get('/laboratorio-mesa', fn () => view('laboratorio-mesa', [
        'tiradas' => collect(TipoLectura::tiradas())->mapWithKeys(fn (TipoLectura $t) => [
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

// Sala privada: {consulta} se resuelve por uuid. La sala de espera y el panel
// de atención (cómo se llega acá) llegan en la Fase 2.
Route::middleware('auth')->prefix('c/{consulta}')->name('consultas.')->scopeBindings()->group(function () {
    Route::get('/', [ConsultaController::class, 'show'])->name('show')->can('ver', 'consulta');
    Route::get('estado', [ConsultaController::class, 'estado'])->name('estado')->can('ver', 'consulta');

    // scopeBindings: {lectura} tiene que ser de ESTA consulta (si no, 404).
    Route::prefix('lecturas/{lectura}')->name('lecturas.')->group(function () {
        Route::post('mezclar', [LecturaController::class, 'mezclar'])->name('mezclar')->can('mezclar', 'lectura');
        Route::post('elegir', [LecturaController::class, 'elegir'])->name('elegir')->can('elegir', 'lectura');
        Route::post('revelar', [LecturaController::class, 'revelar'])->name('revelar')->can('revelar', 'lectura');
    });
});

require __DIR__.'/settings.php';
