{{-- Inicio: la carta 3D girando y los dos caminos (consultante / tarotista). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} · Lecturas de tarot en tiempo real</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/inicio/inicio.js'])
</head>
<body class="min-h-dvh overflow-hidden bg-noche bg-[radial-gradient(ellipse_at_70%_40%,_#2A1E4A_0%,_#0E0B1A_65%)] font-sans text-marfil">
    <div id="fondo" class="fixed inset-0" data-frente="{{ asset('cartas/mayor-17.webp') }}" aria-hidden="true"></div>
    <div data-sonido class="fixed right-4 top-4 z-10"></div>

    <main class="relative mx-auto flex min-h-dvh max-w-6xl items-end px-6 pb-16 md:items-center md:pb-0">
        <div class="max-w-md space-y-6">
            <p class="text-sm uppercase tracking-[0.3em] text-dorado/80">Lecturas en tiempo real</p>
            <h1 class="font-titulo text-5xl leading-tight text-dorado md:text-6xl">Mesa Arcana</h1>
            <p class="text-lg text-marfil/75">
                Una tarotista y vos, frente a la misma mesa a la luz de una vela:
                mezclan, elegís tus cartas y se revelan en las dos pantallas a la vez.
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('espera') }}" class="rounded-lg border border-dorado bg-violeta px-5 py-3 font-semibold transition hover:bg-violeta-claro">Quiero una consulta</a>
                <a href="{{ auth()->user()?->esTarotista() ? route('atencion') : route('login') }}" class="rounded-lg border border-dorado/50 px-5 py-3 font-semibold text-marfil/85 transition hover:bg-violeta/60">Soy tarotista</a>
            </div>
        </div>
    </main>
</body>
</html>
