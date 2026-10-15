{{-- Resumen de la consulta: lecturas (SOLO cartas reveladas) y la conversación.
     Todo lo que escribió una persona va con {{ }}: Blade lo escapa, nunca se interpreta como HTML. --}}
@extends('layouts.mesa')

@section('titulo', 'Resumen')

@section('contenido')
    <article class="space-y-10">
        <header class="space-y-1">
            <p class="text-sm uppercase tracking-widest text-[#C9A54C]">Resumen de la consulta</p>
            <h1 class="text-2xl font-semibold">{{ $consulta->consultante->name }} con {{ $consulta->tarotista->name }}</h1>
            <p class="text-sm text-[#F3EBDD]/60">{{ $consulta->created_at->timezone('America/Argentina/Buenos_Aires')->translatedFormat('j \d\e F \d\e Y, H:i') }} (hora argentina)</p>
        </header>

        @forelse ($consulta->lecturas as $lectura)
            <section class="space-y-4">
                <h2 class="text-lg font-semibold text-[#C9A54C]">{{ $lectura->tipo->nombre() }}</h2>
                @if ($lectura->pregunta)
                    <p class="italic text-[#F3EBDD]/80">{{ $lectura->pregunta->tema->icono }} «{{ $lectura->pregunta->texto }}»</p>
                @endif

                @if ($lectura->tipo === \App\Enums\TipoLectura::CartaAstral)
                    @forelse ($lectura->resultado['puntos'] ?? [] as $clave => $punto)
                        <div class="rounded-xl border border-[#C9A54C]/30 p-4">
                            <p class="text-xs uppercase tracking-widest text-[#C9A54C]">{{ ['sol' => 'Sol', 'luna' => 'Luna', 'ascendente' => 'Ascendente'][$clave] }}</p>
                            <p class="font-semibold">{{ $punto['signo_nombre'] }} {{ $punto['grado'] }}{{ $punto['aproximada'] ? ' (aproximada)' : '' }}</p>
                            <p class="text-sm">{{ $punto['interpretacion'] }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-[#F3EBDD]/50">No se llegaron a cargar los datos de nacimiento.</p>
                    @endforelse
                @else
                @forelse ($lectura->cartas as $elegida)
                    <div class="flex gap-4 rounded-xl border border-[#C9A54C]/30 p-4">
                        <img src="{{ $elegida->carta->urlImagen() }}" alt="{{ $elegida->carta->nombre }}"
                             class="h-28 w-auto rounded {{ $elegida->invertida ? 'rotate-180' : '' }}">
                        <div class="space-y-1">
                            <p class="text-xs uppercase tracking-widest text-[#C9A54C]">{{ $elegida->setRelation('lectura', $lectura)->nombrePosicion() }}</p>
                            <p class="font-semibold">{{ $elegida->carta->nombre }}</p>
                            <p class="text-xs text-[#C97B8E]">{{ $elegida->invertida ? 'Invertida' : 'Al derecho' }}</p>
                            <p class="text-sm">{{ $elegida->invertida ? $elegida->carta->significado_invertido : $elegida->carta->significado_derecho }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-[#F3EBDD]/50">No se llegó a revelar ninguna carta.</p>
                @endforelse
                @endif
            </section>
        @empty
            <p class="text-[#F3EBDD]/60">No se hicieron lecturas.</p>
        @endforelse

        <section class="space-y-2">
            <h2 class="text-lg font-semibold text-[#C9A54C]">La conversación</h2>
            @forelse ($consulta->mensajes as $mensaje)
                @if ($mensaje->autora)
                    <p><strong>{{ $mensaje->autora->name }}:</strong> {{ $mensaje->tipo === \App\Enums\TipoMensaje::PedidoServicio ? '✦ pidió '.$mensaje->servicio->nombre().($mensaje->contenido !== '' ? ' — '.$mensaje->contenido : '') : $mensaje->contenido }}</p>
                @else
                    <p class="text-center text-sm text-[#C9A54C]/70">{{ $mensaje->contenido }}</p>
                @endif
            @empty
                <p class="text-[#F3EBDD]/60">No hubo mensajes.</p>
            @endforelse
        </section>

        <p class="print:hidden"><button onclick="window.print()" class="boton">Imprimir / guardar PDF</button></p>
    </article>
@endsection
