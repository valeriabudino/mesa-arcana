// Música y efectos de sonido, generados por código con Web Audio: no hay archivos de audio.
//
// El navegador no deja sonar nada hasta que la persona toca la página, así que el
// contexto de audio se crea con el primer gesto (clic, toque o tecla). Si la música
// está activada, arranca ahí. Las preferencias (música / efectos) se recuerdan.
//
// Los botones se montan solos en cualquier elemento con [data-sonido].

const PREFERENCIAS = 'mesa-arcana:sonido';

// Re menor → Si♭ → Sol menor → La (la dominante, con el do# que le da el aire "antiguo").
// En MIDI: 69 = La 440.
const ACORDES = [
    [50, 57, 62, 65, 69],
    [46, 53, 62, 65, 69],
    [43, 50, 58, 62, 65],
    [45, 52, 57, 61, 64],
];
const CAMPANITAS = [62, 65, 67, 69, 72, 74, 77, 79, 81]; // pentatónica de Re menor, aguda
const DURACION_ACORDE = 14; // segundos
const hz = (midi) => 440 * 2 ** ((midi - 69) / 12);
const entre = (min, max) => min + Math.random() * (max - min);

const prefs = leerPreferencias();
const oyentes = new Set();

let ctx = null;
let ruido; // buffer de ruido blanco, base de todos los efectos de papel
let bus = {}; // musica, efectos, campanas: cada uno con su cantidad de eco
let musica = null;

function leerPreferencias() {
    try {
        return { musica: true, efectos: true, ...JSON.parse(localStorage.getItem(PREFERENCIAS)) };
    } catch {
        return { musica: true, efectos: true };
    }
}

function guardarPreferencias() {
    try {
        localStorage.setItem(PREFERENCIAS, JSON.stringify(prefs));
    } catch {
        // modo privado o almacenamiento bloqueado: se recuerda solo mientras dure la página
    }
}

/** Respuesta de una sala grande: ruido estéreo que se apaga. Es el eco de todo. */
function impulso(segundos, caida) {
    const largo = Math.round(ctx.sampleRate * segundos);
    const buffer = ctx.createBuffer(2, largo, ctx.sampleRate);
    for (let canal = 0; canal < 2; canal++) {
        const datos = buffer.getChannelData(canal);
        for (let i = 0; i < largo; i++) datos[i] = (Math.random() * 2 - 1) * (1 - i / largo) ** caida;
    }

    return buffer;
}

function crearBus(salida, eco, humedad, volumen) {
    const entrada = ctx.createGain();
    entrada.gain.value = volumen;
    entrada.connect(salida);
    const envio = ctx.createGain();
    envio.gain.value = humedad;
    entrada.connect(envio).connect(eco);

    return entrada;
}

function despertar() {
    if (ctx) {
        if (ctx.state === 'suspended' && !document.hidden) ctx.resume();

        return;
    }
    const Contexto = window.AudioContext ?? window.webkitAudioContext;
    if (!Contexto) return;

    ctx = new Contexto();
    const salida = ctx.createDynamicsCompressor(); // que nada sature si suenan muchas cosas juntas
    salida.connect(ctx.destination);
    const eco = ctx.createConvolver();
    eco.buffer = impulso(3.8, 2.4);
    eco.connect(salida);

    bus = {
        musica: crearBus(salida, eco, 0.9, 0.9),
        efectos: crearBus(salida, eco, 0.12, 1),
        campanas: crearBus(salida, eco, 0.8, 0.7),
    };

    ruido = ctx.createBuffer(1, ctx.sampleRate * 2, ctx.sampleRate);
    const datos = ruido.getChannelData(0);
    for (let i = 0; i < datos.length; i++) datos[i] = Math.random() * 2 - 1;

    if (prefs.musica) arrancarMusica();
}

['pointerdown', 'keydown'].forEach((evento) => window.addEventListener(evento, despertar, { passive: true }));
document.addEventListener('visibilitychange', () => {
    if (!ctx) return;
    document.hidden ? ctx.suspend() : ctx.resume();
});

// --- Instrumentos ---

/** Campana: un seno con parciales inarmónicos que se apaga solo. */
function campana(destino, frecuencia, t, volumen, largo = 3.5) {
    const envolvente = ctx.createGain();
    envolvente.gain.setValueAtTime(0.0001, t);
    envolvente.gain.exponentialRampToValueAtTime(volumen, t + 0.01);
    envolvente.gain.exponentialRampToValueAtTime(0.0001, t + largo);
    const paneo = ctx.createStereoPanner();
    paneo.pan.value = entre(-0.6, 0.6);
    envolvente.connect(paneo).connect(destino);

    for (const [proporcion, intensidad] of [[1, 1], [2.76, 0.35], [5.4, 0.14], [8.93, 0.05]]) {
        const osc = ctx.createOscillator();
        osc.frequency.value = frecuencia * proporcion;
        const nivel = ctx.createGain();
        nivel.gain.value = intensidad;
        osc.connect(nivel).connect(envolvente);
        osc.start(t);
        osc.stop(t + largo + 0.05);
    }
}

/** Un acorde del colchón: cada nota con dos osciladores apenas desafinados, entrada y salida lentas. */
function acorde(destino, notas, t, duracion) {
    const entrada = 5;
    const salida = 6;
    for (const nota of notas) {
        const envolvente = ctx.createGain();
        envolvente.gain.setValueAtTime(0, t);
        envolvente.gain.linearRampToValueAtTime(0.02, t + entrada);
        envolvente.gain.setValueAtTime(0.02, t + duracion);
        envolvente.gain.linearRampToValueAtTime(0, t + duracion + salida);
        const paneo = ctx.createStereoPanner();
        paneo.pan.value = entre(-0.5, 0.5);
        envolvente.connect(paneo).connect(destino);

        for (const [tipo, desafinado] of [['sawtooth', -7], ['triangle', 6]]) {
            const osc = ctx.createOscillator();
            osc.type = tipo;
            osc.frequency.value = hz(nota);
            osc.detune.value = desafinado;
            osc.connect(envolvente);
            osc.start(t);
            osc.stop(t + duracion + salida + 0.1);
        }
    }
}

function arrancarMusica() {
    if (musica || !ctx) return;

    // Un solo filtro para todo el colchón; un LFO muy lento lo abre y lo cierra (respira).
    const filtro = ctx.createBiquadFilter();
    filtro.type = 'lowpass';
    filtro.frequency.value = 650;
    filtro.Q.value = 0.6;
    const lfo = ctx.createOscillator();
    lfo.frequency.value = 0.06;
    const profundidad = ctx.createGain();
    profundidad.gain.value = 280;
    lfo.connect(profundidad).connect(filtro.frequency);
    lfo.start();

    const volumen = ctx.createGain();
    volumen.gain.setValueAtTime(0, ctx.currentTime);
    volumen.gain.linearRampToValueAtTime(1, ctx.currentTime + 3);
    filtro.connect(volumen).connect(bus.musica);

    // Los timers del navegador no son precisos: cada segundo se programa con
    // anticipación lo que va a sonar en los próximos segundos, en el reloj del audio.
    let indice = 0;
    let proximoAcorde = ctx.currentTime + 0.05;
    let proximaCampanita = ctx.currentTime + 3;
    const programar = () => {
        const horizonte = ctx.currentTime + 4;
        while (proximoAcorde < horizonte) {
            acorde(filtro, ACORDES[indice++ % ACORDES.length], proximoAcorde, DURACION_ACORDE);
            proximoAcorde += DURACION_ACORDE;
        }
        while (proximaCampanita < horizonte) {
            const nota = CAMPANITAS[Math.floor(Math.random() * CAMPANITAS.length)];
            campana(volumen, hz(nota), proximaCampanita, entre(0.025, 0.05), 4);
            proximaCampanita += entre(2.5, 7);
        }
    };
    programar();
    const reloj = setInterval(programar, 1000);

    musica = {
        parar() {
            clearInterval(reloj);
            const t = ctx.currentTime;
            volumen.gain.cancelScheduledValues(t);
            volumen.gain.setValueAtTime(volumen.gain.value, t);
            volumen.gain.linearRampToValueAtTime(0, t + 1.5);
            setTimeout(() => {
                lfo.stop();
                volumen.disconnect();
            }, 1700);
        },
    };
}

// --- Efectos: todos salen del mismo ruido blanco, filtrado distinto ---

/** Roce de papel: ruido por un pasabanda que sube (o baja) de tono. */
function roce(t, { duracion = 0.3, desde = 700, hasta = 2600, volumen = 0.22, q = 1.1 } = {}) {
    const fuente = ctx.createBufferSource();
    fuente.buffer = ruido;
    const filtro = ctx.createBiquadFilter();
    filtro.type = 'bandpass';
    filtro.Q.value = q;
    filtro.frequency.setValueAtTime(desde, t);
    filtro.frequency.exponentialRampToValueAtTime(hasta, t + duracion);
    const envolvente = ctx.createGain();
    envolvente.gain.setValueAtTime(0.0001, t);
    envolvente.gain.exponentialRampToValueAtTime(volumen, t + duracion * 0.35);
    envolvente.gain.exponentialRampToValueAtTime(0.0001, t + duracion);
    fuente.connect(filtro).connect(envolvente).connect(bus.efectos);
    fuente.start(t, Math.random() * 1.5);
    fuente.stop(t + duracion + 0.05);
}

/** Golpecito seco: una carta que cae sobre otra o sobre el paño. */
function toque(t, volumen = 0.2, tono = 2400) {
    const fuente = ctx.createBufferSource();
    fuente.buffer = ruido;
    const filtro = ctx.createBiquadFilter();
    filtro.type = 'bandpass';
    filtro.frequency.value = tono * entre(0.85, 1.15);
    filtro.Q.value = 0.9;
    const envolvente = ctx.createGain();
    envolvente.gain.setValueAtTime(volumen, t);
    envolvente.gain.exponentialRampToValueAtTime(0.0001, t + 0.045);
    fuente.connect(filtro).connect(envolvente).connect(bus.efectos);
    fuente.start(t, Math.random() * 1.5);
    fuente.stop(t + 0.06);
}

/** Muchos toques seguidos: el "brrrr" de barajar o de desplegar el mazo. */
function cascada(t, duracion, { porSegundo = 45, volumen = 0.14, tono = 2600 } = {}) {
    const cantidad = Math.max(1, Math.round(duracion * porSegundo));
    for (let k = 0; k < cantidad; k++) {
        toque(t + (k / porSegundo) * entre(0.9, 1.1), volumen * entre(0.5, 1), tono);
    }
}

// --- API ---

const listo = (tipo = 'efectos') => ctx !== null && ctx.state === 'running' && prefs[tipo];

export const sonido = {
    /** Una carta que sale volando (elegirla, juntarla). */
    deslizar(retraso = 0) {
        if (!listo()) return;
        roce(ctx.currentTime + retraso, { duracion: 0.35, desde: 600, hasta: 2800 });
    },
    /** La carta toca la mesa. */
    apoyar(retraso = 0) {
        if (!listo()) return;
        toque(ctx.currentTime + retraso, 0.28, 1500);
    },
    /** Barajar: el riffle de las pilas al intercalarse. */
    barajar(duracion) {
        if (!listo()) return;
        const t = ctx.currentTime;
        roce(t, { duracion: 0.25, desde: 2400, hasta: 900, volumen: 0.12 });
        cascada(t + 0.05, duracion, { porSegundo: 55, volumen: 0.16 });
    },
    /** Cortar el mazo en pilas. */
    cortar() {
        if (!listo()) return;
        const t = ctx.currentTime;
        [0, 0.08, 0.16].forEach((d) => roce(t + d, { duracion: 0.3, desde: 900, hasta: 2200, volumen: 0.14 }));
    },
    /** Desplegar o recoger el abanico: más suave y más largo que barajar. */
    desplegar(duracion) {
        if (!listo()) return;
        const t = ctx.currentTime;
        roce(t, { duracion: Math.min(duracion, 1.2), desde: 500, hasta: 3200, volumen: 0.1, q: 0.7 });
        cascada(t, duracion, { porSegundo: 30, volumen: 0.07, tono: 3200 });
    },
    /** Dar vuelta una carta: un "fsst" corto y una campanita. */
    revelar() {
        if (!listo()) return;
        const t = ctx.currentTime;
        roce(t, { duracion: 0.22, desde: 3200, hasta: 1200, volumen: 0.2, q: 1.5 });
        const nota = CAMPANITAS[3 + Math.floor(Math.random() * 4)];
        campana(bus.campanas, hz(nota), t + 0.15, 0.12, 4);
        campana(bus.campanas, hz(nota + 7), t + 0.27, 0.06, 4);
    },
    /** Un acorde de campanas para los momentos importantes (el cielo, el final). */
    momento() {
        if (!listo()) return;
        const t = ctx.currentTime;
        [62, 69, 74, 77].forEach((nota, k) => campana(bus.campanas, hz(nota), t + k * 0.12, 0.08, 5));
    },
    /** Pasar el mouse por una carta del abanico. Casi imperceptible. */
    rozar() {
        if (!listo()) return;
        toque(ctx.currentTime, 0.05, 3800);
    },

    preferencias: () => ({ ...prefs }),

    alternar(tipo) {
        prefs[tipo] = !prefs[tipo];
        guardarPreferencias();
        despertar();
        if (tipo === 'musica') {
            if (prefs.musica) arrancarMusica();
            else {
                musica?.parar();
                musica = null;
            }
        }
        oyentes.forEach((oyente) => oyente(sonido.preferencias()));
    },

    alCambiar(oyente) {
        oyentes.add(oyente);
        oyente(sonido.preferencias());

        return () => oyentes.delete(oyente);
    },
};

// --- Botones ---

const ICONOS = {
    musica: '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    efectos: '<path d="M11 5 6 9H2v6h4l5 4V5Z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a10 10 0 0 1 0 14"/>',
};
const NOMBRES = { musica: 'Música', efectos: 'Sonidos de las cartas' };

function montarControles(contenedor) {
    contenedor.classList.add('flex', 'gap-1.5');
    for (const tipo of ['musica', 'efectos']) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'grid size-8 place-items-center rounded-full border border-[#C9A54C]/50 bg-[#0E0B1A]/60 text-[#C9A54C] transition hover:bg-[#2A1E4A] aria-[pressed=false]:opacity-45';
        boton.innerHTML = `<svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONOS[tipo]}</svg>`;
        boton.addEventListener('click', () => sonido.alternar(tipo));
        contenedor.append(boton);
        sonido.alCambiar((estado) => {
            boton.setAttribute('aria-pressed', String(estado[tipo]));
            const accion = estado[tipo] ? 'Apagar' : 'Prender';
            boton.title = `${accion} ${NOMBRES[tipo].toLowerCase()}`;
            boton.setAttribute('aria-label', NOMBRES[tipo]);
        });
    }
}

document.querySelectorAll('[data-sonido]').forEach(montarControles);
