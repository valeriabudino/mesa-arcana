{{-- La mesa en tiempo real (Fase 4). El diseño fino llega en la Fase 5.
     Ojo: acá NUNCA se imprime orden_mazo ni datos de cartas sin revelar.
     Las cartas llegan por GET /s/{uuid}/estado y por los eventos de Reverb. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mesa · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/mesa/mesa.js'])
</head>
<body class="h-dvh overflow-hidden bg-[#0E0B1A] font-sans text-[#F3EBDD]">
    {{-- En pantallas anchas el canvas termina donde empieza el panel: la cámara se encuadra sola. --}}
    <div id="lienzo" class="absolute inset-0 md:right-[21rem]"></div>

    <div id="sin-webgl" hidden class="absolute inset-0 grid place-items-center p-6 text-center">
        Tu navegador no tiene WebGL 2, así que no se puede dibujar la mesa 3D.
    </div>

    {{-- Barra de presencia y estado --}}
    <header class="absolute inset-x-0 top-0 flex flex-wrap items-center gap-x-5 gap-y-2 bg-gradient-to-b from-[#0E0B1A] to-transparent px-4 py-3 text-sm">
        <span data-presencia="tarotista" class="flex items-center gap-2">
            <i class="size-2.5 rounded-full bg-zinc-600"></i>
            <span>{{ $sesion->tarotista->name }}</span>
            <span class="text-[#F3EBDD]/50">tarotista</span>
        </span>
        <span data-presencia="consultante" class="flex items-center gap-2">
            <i class="size-2.5 rounded-full bg-zinc-600"></i>
            <span data-nombre>{{ $sesion->consultante?->name ?? '—' }}</span>
            <span class="text-[#F3EBDD]/50">consultante</span>
        </span>
        <span class="ml-auto text-[#F3EBDD]/60">{{ $sesion->tipo_tirada->nombre() }}</span>
        <strong id="estado" class="rounded-full border border-[#C97B8E]/50 px-3 py-1 text-[#C97B8E]">{{ $sesion->estado->etiqueta() }}</strong>
    </header>

    <div id="conexion" hidden class="absolute left-1/2 top-16 -translate-x-1/2 rounded-full bg-[#C97B8E] px-4 py-1.5 text-sm font-semibold text-[#0E0B1A]">
        Reconectando…
    </div>

    {{-- Panel lateral: lo que se va revelando --}}
    <aside class="absolute bottom-24 right-3 top-16 hidden w-80 flex-col gap-3 overflow-auto rounded-xl border border-[#C9A54C]/30 bg-[#0E0B1A]/85 p-4 text-sm backdrop-blur md:flex">
        @if ($sesion->pregunta)
            <p class="italic text-[#F3EBDD]/80">«{{ $sesion->pregunta }}»</p>
        @endif
        <ol id="reveladas" class="space-y-4"></ol>
        <p id="sin-reveladas" class="text-[#F3EBDD]/50">Todavía no hay cartas reveladas.</p>
    </aside>

    {{-- Barra de acción: cambia según rol × estado --}}
    <footer id="acciones" class="absolute inset-x-0 bottom-0 flex flex-wrap items-center justify-center gap-3 bg-gradient-to-t from-[#0E0B1A] to-transparent p-4 text-sm"></footer>

    <div id="aviso" hidden role="alert" class="absolute bottom-20 left-1/2 -translate-x-1/2 rounded-lg border border-[#C97B8E] bg-[#0E0B1A] px-4 py-2 text-sm"></div>

    <style>
        .boton { border-radius: .5rem; border: 1px solid rgb(201 165 76 / .6); background: #2A1E4A; padding: .55rem 1.1rem; font-weight: 600; }
        .boton:hover:not(:disabled) { background: #3A2A63; }
        .boton:disabled { opacity: .4; cursor: wait; }
    </style>

    <script type="application/json" id="datos-mesa">@json($config)</script>
</body>
</html>
