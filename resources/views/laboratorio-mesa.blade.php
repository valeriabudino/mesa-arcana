{{-- Fase 3: laboratorio de la escena 3D. Sin sockets: los botones fabrican el mismo
     payload que mandaría Reverb y se lo pasan a las animaciones. Solo existe en local. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laboratorio de la mesa · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/mesa/laboratorio.js'])
</head>
<body class="h-dvh overflow-hidden bg-[#0E0B1A] font-sans text-[#F3EBDD]">
    <div id="lienzo" class="absolute inset-0"></div>

    <div id="sin-webgl" hidden class="absolute inset-0 grid place-items-center p-6 text-center">
        Tu navegador no tiene WebGL 2, así que no se puede dibujar la mesa 3D.
    </div>

    <aside class="absolute left-3 top-3 w-72 space-y-3 rounded-xl border border-[#C9A54C]/30 bg-[#0E0B1A]/85 p-4 text-sm backdrop-blur">
        <div>
            <h1 class="text-base font-semibold text-[#C9A54C]">Laboratorio de la mesa</h1>
            <p class="text-xs text-[#F3EBDD]/60">Fase 3 · sin sockets. Cada botón arma el payload del evento y lo anima.</p>
        </div>

        <label class="block">
            <span class="text-xs text-[#F3EBDD]/70">Tirada</span>
            <select id="tipo" class="mt-1 w-full rounded-md border border-[#C9A54C]/40 bg-[#2A1E4A] px-2 py-1.5">
                @foreach ($tiradas as $valor => $tirada)
                    <option value="{{ $valor }}" @selected($valor === 'tres_cartas')>{{ $tirada['nombre'] }} ({{ count($tirada['posiciones']) }})</option>
                @endforeach
            </select>
        </label>

        <p>Estado: <strong id="estado" class="text-[#C97B8E]"></strong></p>

        <div class="grid grid-cols-2 gap-2">
            <label class="col-span-2 flex items-center gap-2">
                <span class="text-xs text-[#F3EBDD]/70">Semilla</span>
                <input id="semilla" type="number" min="1" placeholder="al azar" class="w-full rounded-md border border-[#C9A54C]/40 bg-[#2A1E4A] px-2 py-1">
            </label>
            <button data-accion="mezclar" class="col-span-2">Mezclar</button>
            <button data-accion="elegir">Elegir al azar</button>
            <button data-accion="remoto">Hover remoto</button>
            <button data-accion="revelar">Revelar</button>
            <button data-accion="finalizar">Finalizar</button>
            <button data-accion="reiniciar" class="col-span-2 !bg-transparent">Reiniciar</button>
        </div>
        <p class="text-xs text-[#F3EBDD]/60">Con el abanico abierto también podés tocar una carta para elegirla.</p>
    </aside>

    <pre id="registro" class="absolute bottom-3 right-3 max-h-[45dvh] w-96 max-w-[calc(100vw-1.5rem)] overflow-auto rounded-xl border border-[#C9A54C]/30 bg-[#0E0B1A]/85 p-3 text-[11px] leading-snug text-[#F3EBDD]/80"></pre>

    <style>
        [data-accion] { border-radius: .375rem; border: 1px solid rgb(201 165 76 / .5); background: #2A1E4A; padding: .4rem .5rem; }
        [data-accion]:hover:not(:disabled) { background: #3A2A63; }
        [data-accion]:disabled { opacity: .35; cursor: not-allowed; }
    </style>

    <script type="application/json" id="datos-laboratorio">@json(['tiradas' => $tiradas, 'cartas' => $cartas])</script>
</body>
</html>
