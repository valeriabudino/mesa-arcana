<?php

use App\Http\Controllers\RemoteStyleController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Fase 0: prueba emisor → receptor de la guía (se quitan en la Fase 5)
Route::get('/emisor', [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir', [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
