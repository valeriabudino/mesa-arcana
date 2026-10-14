<?php

use App\Http\Controllers\LecturaController;
use App\Http\Controllers\RemoteStyleController;
use App\Http\Controllers\SesionController;
use App\Models\Sesion;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Fase 0: prueba emisor → receptor de la guía (se quitan en la Fase 5)
Route::get('/emisor', [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir', [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

// Lecturas: {sesion} se resuelve por uuid (Sesion::getRouteKeyName)
Route::middleware('auth')->group(function () {
    Route::post('sesiones', [SesionController::class, 'store'])->name('sesiones.store')->can('create', Sesion::class);

    Route::prefix('s/{sesion}')->name('sesiones.')->group(function () {
        Route::get('/', [SesionController::class, 'show'])->name('show')->can('entrar', 'sesion');
        Route::get('resumen', [SesionController::class, 'resumen'])->name('resumen')->can('verResumen', 'sesion');

        Route::post('mezclar', [LecturaController::class, 'mezclar'])->name('mezclar')->can('mezclar', 'sesion');
        Route::post('elegir', [LecturaController::class, 'elegir'])->name('elegir')->can('elegir', 'sesion');
        Route::post('revelar', [LecturaController::class, 'revelar'])->name('revelar')->can('revelar', 'sesion');
        Route::post('finalizar', [LecturaController::class, 'finalizar'])->name('finalizar')->can('finalizar', 'sesion');
    });
});

require __DIR__.'/settings.php';
