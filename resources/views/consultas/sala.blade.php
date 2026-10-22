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
    @fonts
    @vite(['resources/css/app.css', 'resources/js/mesa/mesa.js'])
</head>
{{-- En el celu todo va en columna (barra, mesa, panel, acciones) y el canvas ocupa solo el
     espacio libre; en pantallas anchas el canvas termina donde empieza el panel.
     Así la cámara siempre se encuadra en lo que de verdad se ve. --}}
<body class="flex h-dvh flex-col overflow-hidden bg-[#0E0B1A] font-sans text-[#F3EBDD] md:block">
    {{-- Barra de presencia y estado --}}
    <header class="relative z-10 flex flex-wrap items-center gap-x-5 gap-y-1.5 bg-gradient-to-b from-[#0E0B1A] to-transparent px-4 py-2 text-sm md:absolute md:gap-y-2 md:py-3 md:inset-x-0 md:top-0">
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
        {{-- El estado y los botones de sonido van juntos: si no entran, bajan juntos de renglón. --}}
        <span class="flex items-center gap-3">
            <span id="saldo" class="text-[#C9A54C]"></span>
            <strong id="estado" class="rounded-full border border-[#C97B8E]/50 px-3 py-1 text-[#C97B8E]">Conectando…</strong>
            <span data-sonido></span>
        </span>
    </header>

    <div id="lienzo" class="relative min-h-0 flex-1 md:absolute md:inset-0 md:right-[21rem]"></div>

    <div id="sin-webgl" hidden class="absolute inset-0 grid place-items-center p-6 text-center">
        Tu navegador no tiene WebGL 2, así que no se puede dibujar la mesa 3D.
    </div>

    <div id="conexion" hidden class="absolute left-1/2 top-16 -translate-x-1/2 rounded-full bg-[#C97B8E] px-4 py-1.5 text-sm font-semibold text-[#0E0B1A]">
        Reconectando…
    </div>

    {{-- El chat: la conversación y también la lectura (cada carta revelada y la carta astral
         llegan como mensajes). En pantallas anchas, a la derecha. En el celu, abajo y plegado:
         se abre tocando la barra. --}}
    <aside id="panel" data-plegado="true" class="relative mx-3 flex h-[45dvh] shrink-0 flex-col overflow-hidden rounded-xl border border-[#C9A54C]/30 bg-[#0E0B1A]/90 text-sm backdrop-blur data-[plegado=true]:h-auto md:absolute md:inset-x-auto md:bottom-3 md:right-3 md:top-16 md:mx-0 md:h-auto md:w-80 md:data-[plegado=false]:h-auto">
        <button id="plegar" type="button" aria-controls="chat-cuerpo" aria-expanded="false" class="flex items-center gap-2 border-b border-[#C9A54C]/30 px-4 py-2.5 text-left md:pointer-events-none">
            <span class="font-semibold text-[#C9A54C]">Chat y lectura</span>
            <span id="no-leidos" hidden class="rounded-full bg-[#C97B8E] px-1.5 text-xs text-[#0E0B1A]"></span>
            <svg viewBox="0 0 24 24" class="ml-auto size-5 text-[#C9A54C] transition-transform md:hidden" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
        </button>

        <div id="chat-cuerpo" class="flex min-h-0 flex-1 flex-col">
            <ol id="chat-lista" class="flex-1 space-y-2 overflow-auto p-3" aria-live="polite"></ol>
            <p id="escribiendo" class="h-5 px-3 text-xs italic text-[#F3EBDD]/50"></p>
            @if ($consulta->esTarotista(auth()->user()))
                {{-- El borrador de la interpretación (plan v3 §13.6): llega por el canal privado de la
                     tarotista mientras la IA escribe; lo edita y lo publica en el chat. --}}
                <section id="borrador" hidden class="space-y-2 border-t border-[#C9A54C]/30 p-3">
                    <p class="text-xs uppercase tracking-widest text-[#C9A54C]">✨ Interpretación <span class="normal-case tracking-normal text-[#F3EBDD]/50">· borrador, solo lo ves vos</span></p>
                    <textarea id="borrador-texto" rows="6" maxlength="4000" readonly
                              class="w-full resize-y rounded-md border border-[#C9A54C]/40 bg-[#2A1E4A] px-2 py-1.5 text-sm read-only:opacity-80"></textarea>
                    <div class="flex items-center gap-2">
                        <span id="borrador-estado" class="text-xs text-[#F3EBDD]/50"></span>
                        <button id="borrador-publicar" type="button" class="boton ml-auto !px-3 !py-1.5" disabled>Publicar</button>
                    </div>
                </section>
            @endif
            <form id="chat-form" class="flex gap-2 border-t border-[#C9A54C]/30 p-2">
                <input id="chat-texto" maxlength="1000" autocomplete="off" placeholder="Escribí un mensaje…"
                       class="min-w-0 flex-1 rounded-md border border-[#C9A54C]/40 bg-[#2A1E4A] px-2 py-1.5">
                <button class="boton !px-3 !py-1.5">Enviar</button>
            </form>
        </div>
    </aside>

    {{-- Barra de acción: cambia según rol × estado --}}
    <footer id="acciones" class="relative flex shrink-0 flex-wrap items-center justify-center gap-3 bg-gradient-to-t from-[#0E0B1A] to-transparent p-3 text-sm md:absolute md:inset-x-0 md:bottom-0 md:right-[21rem] md:p-4"></footer>

    <div id="aviso" hidden role="alert" class="absolute bottom-20 left-1/2 -translate-x-1/2 rounded-lg border border-[#C97B8E] bg-[#0E0B1A] px-4 py-2 text-sm"></div>

    <style>
        .boton { border-radius: .5rem; border: 1px solid rgb(201 165 76 / .6); background: #2A1E4A; padding: .55rem 1.1rem; font-weight: 600; }
        .boton:hover:not(:disabled) { background: #3A2A63; }
        .boton:disabled { opacity: .4; cursor: wait; }
        @media (max-width: 767.98px) {
            #panel[data-plegado=true] #chat-cuerpo { display: none; }
            #panel[data-plegado=false] #plegar svg { transform: rotate(180deg); }
        }
        .campo-chico { border-radius: .375rem; border: 1px solid rgb(201 165 76 / .4); background: #2A1E4A; padding: .45rem .6rem; }
    </style>

    <script type="application/json" id="datos-mesa">@json($config)</script>
</body>
</html>
