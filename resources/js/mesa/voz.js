// Leer en voz alta la interpretación, con la voz del navegador (Web Speech API).
// No pasa por el servidor: cada pantalla usa las voces de su sistema, así que la voz
// cambia de un equipo a otro. Se elige la mejor en español y se habla despacio y grave.

const sintesis = window.speechSynthesis;

export const hayVoz = Boolean(sintesis && window.SpeechSynthesisUtterance);

// Las voces "naturales" (Edge, Safari) y las de Google suenan mucho menos robóticas.
const PREFERIDAS = /natural|neural|online|google|premium|enhanced|siri/i;

function mejorVoz() {
    const espanol = sintesis.getVoices().filter((v) => v.lang.toLowerCase().startsWith('es'));
    const puntaje = (v) => (PREFERIDAS.test(v.name) ? 4 : 0)
        + (/^es-(ar|uy)/i.test(v.lang) ? 2 : /^es-(419|mx|us)/i.test(v.lang) ? 1 : 0)
        + (v.localService ? 0 : 1);

    return espanol.sort((a, b) => puntaje(b) - puntaje(a))[0] ?? null;
}

// Las voces cargan de a poco: se piden una vez para que estén listas al primer clic.
if (hayVoz) sintesis.getVoices();

let botonActivo = null;

function marcar(boton, leyendo) {
    boton.textContent = leyendo ? '■ Detener' : '🔊 Escuchar';
    boton.setAttribute('aria-pressed', String(leyendo));
}

export function detener() {
    if (!hayVoz) return;
    sintesis.cancel();
    if (botonActivo) marcar(botonActivo, false);
    botonActivo = null;
}

/** Lee el texto; si ese botón ya estaba leyendo, lo corta. */
export function alternarLectura(texto, boton) {
    const eraEste = botonActivo === boton;
    detener();
    if (eraEste) return;

    const voz = mejorVoz();
    // Chrome corta los textos largos a los ~15 s: se lee de a una oración, en fila.
    const partes = texto.match(/[^.!?…\n]+[.!?…]*/g)?.map((p) => p.trim()).filter(Boolean) ?? [];
    if (!partes.length) return;

    botonActivo = boton;
    marcar(boton, true);
    partes.forEach((parte, i) => {
        const frase = new SpeechSynthesisUtterance(parte);
        frase.lang = voz?.lang ?? 'es-AR';
        if (voz) frase.voice = voz;
        frase.rate = 0.88;
        frase.pitch = 0.95;
        if (i === partes.length - 1) {
            frase.onend = () => {
                if (botonActivo === boton) detener();
            };
        }
        sintesis.speak(frase);
    });
}

// Si se cierra o se recarga la página, que no siga hablando.
if (hayVoz) window.addEventListener('pagehide', detener);
