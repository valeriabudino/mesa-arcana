{{-- Esqueleto de la mesa. La escena 3D llega en las Fases 3 y 4; el diseño en la 5.
     Ojo: acá NUNCA se imprime orden_mazo ni datos de cartas sin revelar. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mesa · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="p-6 font-sans">
    <h1 class="text-2xl font-bold">Mesa ({{ $sesion->tipo_tirada->nombre() }})</h1>
    <p>Estado: <strong>{{ $sesion->estado->etiqueta() }}</strong></p>
    <p>Tarotista: {{ $sesion->tarotista->name }}</p>
    <p>Consultante: {{ $sesion->consultante?->name ?? '— esperando —' }}</p>
    @if ($sesion->pregunta)
        <p>Pregunta: «{{ $sesion->pregunta }}»</p>
    @endif
    <p class="mt-4 text-sm text-zinc-500">Link para la consultante: {{ route('sesiones.show', $sesion) }}</p>
</body>
</html>
