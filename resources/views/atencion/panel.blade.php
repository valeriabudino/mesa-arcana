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

    {{-- Con una consulta abierta no se puede atender a nadie más: se explica, para que «Atender» apagado no confunda. --}}
    <div id="aviso-consulta-activa" hidden class="mb-6 space-y-3 rounded-xl border border-[#C9A54C]/50 bg-[#2A1E4A]/60 p-4">
        <p>Tenés una consulta en curso. Para atender a otra persona, entrá y tocá <strong>Finalizar consulta</strong>.</p>
        <a id="consulta-activa" href="#" class="boton">Volver a tu consulta en curso</a>
    </div>

    <h2 class="mb-3 text-sm uppercase tracking-widest text-[#F3EBDD]/60">En la fila</h2>
    <ol id="cola" class="space-y-3"></ol>
    <p id="cola-vacia" class="text-[#F3EBDD]/50">No hay nadie esperando.</p>

    <script type="application/json" id="datos-atencion">@json($config)</script>
@endsection
