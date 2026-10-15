{{-- Panel de atención de la tarotista: la fila en vivo por el canal privado "tarotistas". --}}
@extends('layouts.mesa', ['scripts' => ['resources/js/espera/panelAtencion.js']])

@section('titulo', 'Atención')

@section('contenido')
    <header class="mb-8 flex flex-wrap items-center gap-4">
        <h1 class="text-2xl font-semibold text-[#C9A54C]">Hola, {{ auth()->user()->name }}</h1>
        <label class="ml-auto flex cursor-pointer items-center gap-2">
            <input id="disponible" type="checkbox" @checked(auth()->user()->disponible) class="size-5 accent-[#C9A54C]">
            <span>Disponible</span>
        </label>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-sm text-[#F3EBDD]/60 underline">Cerrar sesión</button>
        </form>
    </header>

    <a id="consulta-activa" hidden href="#" class="boton mb-6">Volver a tu consulta en curso</a>

    <h2 class="mb-3 text-sm uppercase tracking-widest text-[#F3EBDD]/60">En la fila</h2>
    <ol id="cola" class="space-y-3"></ol>
    <p id="cola-vacia" class="text-[#F3EBDD]/50">No hay nadie esperando.</p>

    <script type="application/json" id="datos-atencion">@json($config)</script>
@endsection
