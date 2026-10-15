@extends('layouts.mesa')

@section('titulo', 'Quiero una consulta')

@section('contenido')
    <form method="POST" action="{{ route('turnos.store') }}" class="space-y-5 rounded-xl border border-[#C9A54C]/30 p-6">
        @csrf
        <h1 class="text-2xl font-semibold text-[#C9A54C]">Quiero una consulta</h1>

        @if ($conNombre)
            <label class="block space-y-1">
                <span class="text-sm text-[#F3EBDD]/80">¿Cómo te llamás?</span>
                <input name="nombre" value="{{ old('nombre') }}" required maxlength="40" autofocus class="campo">
            </label>
        @endif

        <label class="block space-y-1">
            <span class="text-sm text-[#F3EBDD]/80">¿Qué te trae por acá? <span class="text-[#F3EBDD]/50">(opcional)</span></span>
            <textarea name="motivo" maxlength="200" rows="3" class="campo">{{ old('motivo') }}</textarea>
        </label>

        @if ($errors->any())
            <p class="text-sm text-[#C97B8E]">{{ $errors->first() }}</p>
        @endif

        <button class="boton">Entrar a la fila</button>
    </form>
@endsection
