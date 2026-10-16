<?php

use App\Enums\TipoLectura;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\CartaAstralController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\EsperaController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\LecturaController;
use App\Models\Carta;
use App\Models\Turno;
use Illuminate\Support\Facades\Route;

Route::get('/', InicioController::class)->name('home');

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

// Fortify manda acá después del login: cada rol a su lugar.
Route::get('dashboard', fn () => redirect()->route(auth()->user()->esTarotista() ? 'atencion' : 'espera'))
    ->middleware('auth')->name('dashboard');

// Sala de espera (consultante). Sin login muestra "¿Cómo te llamás?".
Route::get('espera', [EsperaController::class, 'show'])->name('espera');
Route::post('turnos', [EsperaController::class, 'store'])->name('turnos.store')
    ->middleware('throttle:10,1'); // como mucho 10 por minuto: nadie crea invitadas en masa

Route::middleware('auth')->group(function () {
    Route::get('espera/estado', [EsperaController::class, 'estado'])->name('espera.estado');
    // El uuid del turno es público (viaja en sala-espera): la policy exige ser la dueña.
    Route::post('turnos/{turno}/cancelar', [EsperaController::class, 'cancelar'])->name('turnos.cancelar')->can('cancelar', 'turno');
    Route::post('turnos/{turno}/motivo', [EsperaController::class, 'motivo'])->name('turnos.motivo')->can('cancelar', 'turno'); // misma regla: solo la dueña

    // Panel de atención (tarotista)
    Route::middleware('can:viewAny,'.Turno::class)->group(function () {
        Route::get('atencion', [AtencionController::class, 'show'])->name('atencion');
        Route::get('atencion/cola', [AtencionController::class, 'cola'])->name('atencion.cola');
        Route::post('atencion/disponible', [AtencionController::class, 'disponible'])->name('atencion.disponible');
    });
    Route::post('turnos/{turno}/tomar', [AtencionController::class, 'tomar'])->name('turnos.tomar')->can('tomar', 'turno');
});

// Sala privada: {consulta} se resuelve por uuid. Se llega desde la sala de espera
// (TuTarotistaTeEspera) o desde el panel de atención (Atender).
Route::middleware('auth')->prefix('c/{consulta}')->name('consultas.')->scopeBindings()->group(function () {
    Route::get('/', [ConsultaController::class, 'show'])->name('show')->can('ver', 'consulta');
    Route::get('estado', [ConsultaController::class, 'estado'])->name('estado')->can('ver', 'consulta');
    Route::get('resumen', [ConsultaController::class, 'resumen'])->name('resumen')->can('ver', 'consulta');
    Route::post('finalizar', [ConsultaController::class, 'finalizar'])->name('finalizar')->can('conducir', 'consulta');
    Route::post('ausente', [ConsultaController::class, 'ausente'])->name('ausente')->can('conducir', 'consulta');

    // Chat
    Route::get('mensajes', [ChatController::class, 'index'])->name('mensajes')->can('ver', 'consulta');
    Route::post('mensajes', [ChatController::class, 'store'])->name('mensajes.store')->can('escribir', 'consulta')
        ->middleware('throttle:20,1'); // anti-spam: 20 por minuto
    Route::post('pedidos', [ChatController::class, 'pedir'])->name('pedidos')->can('pedir', 'consulta');
    // scopeBindings: {mensaje} tiene que ser de ESTA consulta.
    Route::post('pedidos/{mensaje}/aceptar', [ChatController::class, 'aceptar'])->name('pedidos.aceptar')->can('conducir', 'consulta');
    Route::post('pedidos/{mensaje}/rechazar', [ChatController::class, 'rechazar'])->name('pedidos.rechazar')->can('conducir', 'consulta');
    // La consultante retira su pedido pendiente (ChatService verifica que sea suyo).
    Route::post('pedidos/{mensaje}/cancelar', [ChatController::class, 'cancelar'])->name('pedidos.cancelar')->can('pedir', 'consulta');

    // scopeBindings: {lectura} tiene que ser de ESTA consulta (si no, 404).
    Route::prefix('lecturas/{lectura}')->name('lecturas.')->group(function () {
        Route::post('mezclar', [LecturaController::class, 'mezclar'])->name('mezclar')->can('mezclar', 'lectura');
        Route::post('elegir', [LecturaController::class, 'elegir'])->name('elegir')->can('elegir', 'lectura');
        Route::post('revelar', [LecturaController::class, 'revelar'])->name('revelar')->can('revelar', 'lectura');
        Route::post('cerrar', [LecturaController::class, 'cerrar'])->name('cerrar')->can('cerrar', 'lectura');
        Route::post('aclaratoria', [LecturaController::class, 'pedirAclaratoria'])->name('aclaratoria')->can('pedirAclaratoria', 'lectura');
        Route::post('nacimiento', [CartaAstralController::class, 'nacimiento'])->name('nacimiento')->can('enviarNacimiento', 'lectura')
            ->middleware('throttle:10,1'); // cada envío corre un proceso de node
        Route::post('senalar', [CartaAstralController::class, 'senalar'])->name('senalar')->can('senalar', 'lectura');
    });
});

require __DIR__.'/settings.php';
