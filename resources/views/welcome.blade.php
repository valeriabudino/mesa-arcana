{{-- Inicio provisorio: la versión con la carta 3D llega en la fase de pantallas. --}}
@extends('layouts.mesa')

@section('titulo', 'Inicio')

@section('contenido')
    <div class="space-y-8 pt-16 text-center">
        <h1 class="text-4xl font-semibold tracking-wide text-[#C9A54C]">Mesa Arcana</h1>
        <p class="text-[#F3EBDD]/70">Lecturas de tarot en tiempo real, a la luz de una vela.</p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="{{ route('espera') }}" class="boton">Quiero una consulta</a>
            <a href="{{ auth()->user()?->esTarotista() ? route('atencion') : route('login') }}" class="boton !bg-transparent">Soy tarotista</a>
        </div>
    </div>
@endsection
