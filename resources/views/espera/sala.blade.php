{{-- Sala de espera. Lo que llega por el canal público es solo la fila de uuids anónimos:
     esta página busca el suyo y calcula la posición. --}}
@extends('layouts.mesa', ['scripts' => ['resources/js/espera/salaEspera.js']])

@section('titulo', 'Sala de espera')

@section('fondo')
    <div id="fondo" class="fixed inset-0 opacity-70" aria-hidden="true"></div>
    <div data-sonido class="fixed right-4 top-4 z-10"></div>
@endsection

@section('contenido')
    <div class="relative space-y-8 pt-[38dvh] text-center md:pt-24">
        <p class="text-sm uppercase tracking-[0.3em] text-dorado/80">Sala de espera</p>
        <p id="posicion" class="font-titulo text-3xl text-marfil transition-transform duration-500" aria-live="polite">Calculando tu lugar en la fila…</p>

        <form id="form-motivo" class="mx-auto flex max-w-md gap-2">
            <input id="motivo" maxlength="200" value="{{ $turno->motivo }}" placeholder="¿Qué te trae por acá? (opcional)"
                   class="campo flex-1 !bg-violeta/70 text-sm" aria-label="Motivo de la consulta">
            <button class="boton !px-3 text-sm">Guardar</button>
        </form>

        <div id="llamado" hidden class="mx-auto max-w-md space-y-4 rounded-xl border border-dorado bg-violeta p-6 shadow-[0_0_60px_rgb(201_165_76/0.35)]">
            <p class="font-titulo text-2xl text-dorado">✦ <span id="llamado-tarotista"></span> te está esperando</p>
            <a id="llamado-link" href="#" class="boton">Entrar ahora</a>
        </div>

        <form id="salir" method="POST" action="{{ route('turnos.cancelar', $turno) }}">
            @csrf
            <button class="text-sm text-marfil/60 underline underline-offset-4 hover:text-marfil">Salir de la fila</button>
        </form>
    </div>

    <script type="application/json" id="datos-espera">@json($config + ['turno' => $turno->uuid, 'rutas_turno' => ['motivo' => route('turnos.motivo', $turno)]])</script>
@endsection
