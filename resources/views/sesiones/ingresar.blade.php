{{-- La consultante entra solo con su nombre. Esqueleto: el diseño llega en la Fase 5. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar a la lectura · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="p-6 font-sans">
    <main class="mx-auto max-w-sm">
        <h1 class="text-2xl font-bold">{{ $sesion->tarotista->name }} te invitó a una lectura</h1>
        <p class="mt-1 text-zinc-500">Tirada: {{ $sesion->tipo_tirada->nombre() }}</p>

        <form method="POST" action="{{ route('sesiones.ingresar', $sesion) }}" class="mt-6 flex flex-col gap-3">
            @csrf
            <label for="nombre" class="font-semibold">¿Cómo te llamás?</label>
            <input id="nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="40" autofocus
                   autocomplete="given-name" class="rounded border border-zinc-300 p-2">
            @error('nombre')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            <button type="submit" class="rounded bg-zinc-900 p-2 text-white">Entrar a la sala</button>
        </form>

        <p class="mt-6 text-sm text-zinc-500">
            ¿Sos la tarotista? <a href="{{ route('login') }}" class="underline">Iniciá sesión</a>
        </p>
    </main>
</body>
</html>
