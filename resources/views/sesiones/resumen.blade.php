{{-- Esqueleto del resumen (se diseña en la Fase 5). Solo muestra cartas YA reveladas. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="p-6 font-sans">
    <h1 class="text-2xl font-bold">Resumen de la lectura</h1>
    @if ($sesion->pregunta)
        <p>Pregunta: «{{ $sesion->pregunta }}»</p>
    @endif
    <ul class="mt-4 space-y-3">
        @foreach ($reveladas as $sc)
            <li>
                <strong>{{ $sesion->tipo_tirada->nombrePosicion($sc->posicion) }}:</strong>
                {{ $sc->carta->nombre }} {{ $sc->invertida ? '(invertida)' : '' }}
                — {{ $sc->invertida ? $sc->carta->significado_invertido : $sc->carta->significado_derecho }}
            </li>
        @endforeach
    </ul>
</body>
</html>
