{{-- Inicio: los dos caminos (consultante / tarotista) y el oráculo del día: la carta 3D
     boca abajo hasta las 9 (hora argentina), cuando se revela en vivo por el canal público. --}}
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
    <div id="fondo" class="fixed inset-0" aria-hidden="true"></div>
    <div data-sonido class="fixed right-4 top-4 z-10"></div>

    <main class="relative mx-auto flex min-h-dvh max-w-6xl items-end px-6 pb-8 md:items-center md:pb-0">
        <div class="max-w-md space-y-4 md:space-y-6">
            <p class="hidden text-sm uppercase tracking-[0.3em] text-dorado/80 md:block">Lecturas en tiempo real</p>
            <h1 class="font-titulo text-4xl leading-tight text-dorado md:text-6xl">Mesa Arcana</h1>
            <p class="text-base text-marfil/75 md:text-lg">
                Una tarotista y vos, frente a la misma mesa a la luz de una vela:
                mezclan, elegís tus cartas y se revelan en las dos pantallas a la vez.
            </p>
            <div class="flex flex-wrap gap-2 md:gap-3">
                <a href="{{ route('espera') }}" class="rounded-lg border border-dorado bg-violeta px-4 py-2.5 font-semibold transition hover:bg-violeta-claro md:px-5 md:py-3">Quiero una consulta</a>
                <a href="{{ auth()->user()?->esTarotista() ? route('atencion') : route('login') }}" class="rounded-lg border border-dorado/50 px-4 py-2.5 font-semibold text-marfil/85 transition hover:bg-violeta/60 md:px-5 md:py-3">Soy tarotista</a>
            </div>

            <section id="oraculo" class="space-y-1 rounded-xl border border-dorado/30 bg-noche/60 p-4 backdrop-blur" aria-live="polite">
                <p class="text-xs uppercase tracking-[0.25em] text-dorado/80">✦ Oráculo del día</p>
                <div data-oculto @if ($oraculo['revelado']) hidden @endif>
                    <p class="text-sm text-marfil/75">La carta de hoy se revela a las 9:00. Faltan:</p>
                    <p data-cuenta class="font-titulo text-xl tabular-nums text-dorado md:text-2xl">&nbsp;</p>
                </div>
                <div data-revelado @unless ($oraculo['revelado']) hidden @endunless>
                    <p class="font-titulo text-xl text-dorado md:text-2xl"><span data-nombre>{{ $oraculo['revelado']['carta']['nombre'] ?? '' }}</span></p>
                    <p data-orientacion class="text-xs text-[#C97B8E]">{{ ($oraculo['revelado']['invertida'] ?? false) ? 'Invertida' : 'Al derecho' }}</p>
                    <p data-mensaje class="text-sm text-marfil/80">{{ $oraculo['revelado']['mensaje'] ?? '' }}</p>
                </div>
            </section>
        </div>
    </main>

    <script type="application/json" id="datos-oraculo">@json($oraculo)</script>
</body>
</html>
