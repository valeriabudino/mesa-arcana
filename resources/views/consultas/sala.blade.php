{{-- La sala privada: presencia + mesa 3D (el chat llega en la Fase 3; el diseño fino, en la 7).
     Ojo: acá NUNCA se imprime orden_mazo ni datos de cartas sin revelar.
     Las cartas llegan por GET /c/{uuid}/estado y por los eventos de Reverb. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Consulta · {{ config('app.name') }}</title>
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
            <span>{{ $consulta->tarotista->name }}</span>
            <span class="text-[#F3EBDD]/50">tarotista</span>
        </span>
        <span data-presencia="consultante" class="flex items-center gap-2">
            <i class="size-2.5 rounded-full bg-zinc-600"></i>
            <span>{{ $consulta->consultante->name }}</span>
            <span class="text-[#F3EBDD]/50">consultante</span>
        </span>
        <span id="tipo-lectura" class="ml-auto text-[#F3EBDD]/60"></span>
        <strong id="estado" class="rounded-full border border-[#C97B8E]/50 px-3 py-1 text-[#C97B8E]">Conectando…</strong>
    </header>

    <div id="conexion" hidden class="absolute left-1/2 top-16 -translate-x-1/2 rounded-full bg-[#C97B8E] px-4 py-1.5 text-sm font-semibold text-[#0E0B1A]">
        Reconectando…
    </div>

    {{-- Panel lateral con dos pestañas: la lectura y el chat. En el celu, abajo. --}}
    <aside class="absolute inset-x-3 bottom-24 flex h-[38dvh] flex-col overflow-hidden rounded-xl border border-[#C9A54C]/30 bg-[#0E0B1A]/90 text-sm backdrop-blur md:inset-x-auto md:right-3 md:top-16 md:h-auto md:w-80">
        <nav class="flex border-b border-[#C9A54C]/30" role="tablist">
            <button data-pestana="lectura" role="tab" class="flex-1 px-3 py-2">Lectura</button>
            <button data-pestana="chat" role="tab" class="flex-1 px-3 py-2">Chat <span id="no-leidos" hidden class="ml-1 rounded-full bg-[#C97B8E] px-1.5 text-xs text-[#0E0B1A]"></span></button>
        </nav>

        <section data-panel="lectura" class="flex-1 space-y-3 overflow-auto p-4">
            <p id="pregunta" hidden class="italic text-[#F3EBDD]/80"></p>
            <ol id="reveladas" class="space-y-4"></ol>
            <p id="sin-reveladas" class="text-[#F3EBDD]/50">Todavía no hay cartas reveladas.</p>
        </section>

        <section data-panel="chat" hidden class="flex min-h-0 flex-1 flex-col">
            <ol id="chat-lista" class="flex-1 space-y-2 overflow-auto p-3" aria-live="polite"></ol>
            <p id="escribiendo" class="h-5 px-3 text-xs italic text-[#F3EBDD]/50"></p>
            <form id="chat-form" class="flex gap-2 border-t border-[#C9A54C]/30 p-2">
                <input id="chat-texto" maxlength="1000" autocomplete="off" placeholder="Escribí un mensaje…"
                       class="min-w-0 flex-1 rounded-md border border-[#C9A54C]/40 bg-[#2A1E4A] px-2 py-1.5">
                <button class="boton !px-3 !py-1.5">Enviar</button>
            </form>
        </section>
    </aside>

    {{-- Barra de acción: cambia según rol × estado --}}
    <footer id="acciones" class="absolute inset-x-0 bottom-0 flex flex-wrap items-center justify-center gap-3 bg-gradient-to-t from-[#0E0B1A] to-transparent p-4 text-sm"></footer>

    <div id="aviso" hidden role="alert" class="absolute bottom-20 left-1/2 -translate-x-1/2 rounded-lg border border-[#C97B8E] bg-[#0E0B1A] px-4 py-2 text-sm"></div>

    <style>
        .boton { border-radius: .5rem; border: 1px solid rgb(201 165 76 / .6); background: #2A1E4A; padding: .55rem 1.1rem; font-weight: 600; }
        .boton:hover:not(:disabled) { background: #3A2A63; }
        .boton:disabled { opacity: .4; cursor: wait; }
        [role=tab][aria-selected=true] { color: #C9A54C; box-shadow: inset 0 -2px #C9A54C; }
        .campo-chico { border-radius: .375rem; border: 1px solid rgb(201 165 76 / .4); background: #2A1E4A; padding: .45rem .6rem; }
    </style>

    <script type="application/json" id="datos-mesa">@json($config)</script>
</body>
</html>
