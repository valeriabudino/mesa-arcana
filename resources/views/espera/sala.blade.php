{{-- Sala de espera. Lo que llega por el canal público es solo la fila de uuids anónimos:
     esta página busca el suyo y calcula la posición. --}}
@extends('layouts.mesa', ['scripts' => ['resources/js/espera/salaEspera.js']])

@section('titulo', 'Sala de espera')

@section('contenido')
    <div class="space-y-8 pt-10 text-center">
        <p class="text-sm uppercase tracking-widest text-[#C9A54C]">Sala de espera</p>
        <p class="text-2xl">
            <span id="posicion">Calculando tu lugar en la fila…</span>
        </p>
        @if ($turno->motivo)
            <p class="italic text-[#F3EBDD]/60">«{{ $turno->motivo }}»</p>
        @endif

        <div id="llamado" hidden class="space-y-4 rounded-xl border border-[#C9A54C] bg-[#2A1E4A] p-6">
            <p class="text-2xl text-[#C9A54C]">✦ <span id="llamado-tarotista"></span> te está esperando</p>
            <a id="llamado-link" href="#" class="boton">Entrar ahora</a>
        </div>

        <form id="salir" method="POST" action="{{ route('turnos.cancelar', $turno) }}">
            @csrf
            <button class="boton !bg-transparent text-sm">Salir de la fila</button>
        </form>
    </div>

    <script type="application/json" id="datos-espera">@json($config + ['turno' => $turno->uuid])</script>
@endsection
