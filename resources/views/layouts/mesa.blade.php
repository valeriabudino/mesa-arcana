{{-- Base de las pantallas propias (inicio, espera, atención). El diseño fino llega en la fase de pantallas. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo') · {{ config('app.name') }}</title>
    @vite(array_merge(['resources/css/app.css'], $scripts ?? []))
    <style>
        .boton { display: inline-block; border-radius: .5rem; border: 1px solid rgb(201 165 76 / .6); background: #2A1E4A; padding: .6rem 1.2rem; font-weight: 600; }
        .boton:hover:not(:disabled) { background: #3A2A63; }
        .boton:disabled { opacity: .4; cursor: not-allowed; }
        .campo { width: 100%; border-radius: .5rem; border: 1px solid rgb(201 165 76 / .4); background: #2A1E4A; padding: .55rem .75rem; }
    </style>
</head>
<body class="min-h-dvh bg-[#0E0B1A] font-sans text-[#F3EBDD]">
    <div id="conexion" hidden class="fixed left-1/2 top-3 -translate-x-1/2 rounded-full bg-[#C97B8E] px-4 py-1.5 text-sm font-semibold text-[#0E0B1A]">Conectando…</div>
    <div id="aviso" hidden role="alert" class="fixed bottom-6 left-1/2 -translate-x-1/2 rounded-lg border border-[#C97B8E] bg-[#0E0B1A] px-4 py-2 text-sm"></div>

    <main class="mx-auto max-w-2xl px-4 py-10">
        @yield('contenido')
    </main>
</body>
</html>
