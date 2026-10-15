// Laboratorio de la Fase 3: la escena 3D sola, sin Echo ni Reverb.
// Un "servidor de mentira" arma los mismos payloads que los eventos reales de la Fase 2
// y se los pasa a las animaciones por una cola, igual que hará mesa.js en la Fase 4.

import { crearAnimaciones } from './animaciones.js';
import { crearCola } from './cola.js';
import { crearEscena, hayWebGL } from './escena.js';
import { crearInteraccion } from './interaccion.js';
import { crearMazo } from './mazo.js';

const { tiradas, cartas } = JSON.parse(document.getElementById('datos-laboratorio').textContent);
const $ = (selector) => document.querySelector(selector);
const azarEntero = (max) => Math.floor(Math.random() * max);

if (!hayWebGL()) {
    $('#sin-webgl').hidden = false;
    throw new Error('Sin WebGL 2');
}

const escena = crearEscena($('#lienzo'));
const mazo = crearMazo(escena);

let animaciones;
let encolar;
let servidor;
let hoverPropio = null;
let pendientes = 0; // animaciones en la cola (para saber cuándo terminó todo)

// --- "Servidor" de mentira: imita a LecturaService (estados, 409, qué viaja en cada evento) ---

function reiniciar() {
    const tipo = $('#tipo').value;
    animaciones?.reiniciar();
    animaciones = crearAnimaciones({ escena, mazo, tipoTirada: tipo });
    encolar = crearCola();
    pendientes = 0;
    servidor = {
        tipo,
        estado: 'lista',
        // En el servidor real esto es orden_mazo, que NUNCA sale de PHP. Acá vive en el
        // laboratorio solo para poder fabricar el payload de CartaRevelada.
        orden: [...cartas].sort(() => Math.random() - 0.5),
        elegidas: [],
    };
    hoverPropio = null;
    actualizarBotones();
}

function emitir(nombre, payload, animar) {
    registrar(`${nombre} ${JSON.stringify(payload, null, 1)}`);
    pendientes++;
    encolar(() => animar(payload)).finally(() => {
        pendientes--;
        actualizarBotones();
    });
}

function rechazar(motivo) {
    registrar(`409 · ${motivo}`);
}

const acciones = {
    mezclar() {
        if (servidor.estado !== 'lista') return rechazar('solo se mezcla con la sesión lista');
        servidor.estado = 'eligiendo';
        const semilla = Number($('#semilla').value) || 1 + azarEntero(2_147_483_647);
        emitir('MazoMezclado', { semilla_animacion: semilla }, async (e) => {
            await animaciones.mezclar(e);
            await animaciones.abanico();
        });
    },

    elegir(indice = null) {
        if (servidor.estado !== 'eligiendo') return rechazar('no es momento de elegir');
        const libres = mazo.cartas.map((_, i) => i).filter((i) => !servidor.elegidas.some((x) => x.indice === i));
        indice ??= libres[azarEntero(libres.length)];
        if (!libres.includes(indice)) return rechazar(`la carta ${indice} ya fue elegida`);

        const posicion = servidor.elegidas.length;
        servidor.elegidas.push({ indice, carta: servidor.orden[indice], invertida: Math.random() < 0.5, revelada: false });
        const completa = servidor.elegidas.length === tiradas[servidor.tipo].posiciones.length;
        if (completa) servidor.estado = 'revelando';

        // Igual que el evento real: SIN datos de la carta.
        emitir('CartaElegida', { posicion, indice_abanico: indice }, async (e) => {
            await animaciones.elegir(e);
            if (completa) await animaciones.recogerAbanico();
        });
    },

    revelar() {
        if (servidor.estado !== 'revelando') return rechazar('no es momento de revelar');
        const posicion = servidor.elegidas.findIndex((x) => !x.revelada);
        if (posicion === -1) return rechazar('ya están todas reveladas');

        const elegida = servidor.elegidas[posicion];
        elegida.revelada = true;
        const { carta, invertida } = elegida;

        // Recién acá viaja la carta.
        emitir(
            'CartaRevelada',
            {
                posicion,
                nombre_posicion: tiradas[servidor.tipo].posiciones[posicion],
                invertida,
                carta: {
                    nombre: carta.nombre,
                    imagen: carta.imagen,
                    significado: invertida ? carta.significado_invertido : carta.significado_derecho,
                    palabras_clave: carta.palabras_clave,
                },
            },
            (e) => animaciones.revelar(e),
        );
    },

    finalizar() {
        if (servidor.estado !== 'revelando' || servidor.elegidas.some((x) => !x.revelada)) {
            return rechazar('faltan cartas por revelar');
        }
        servidor.estado = 'finalizada';
        emitir('SesionFinalizada', { url_resumen: '/s/…/resumen' }, () => animaciones.finalizar());
    },

    remoto() {
        // En la Fase 4 esto llega por whisper: la otra persona pasa el mouse por una carta.
        const libres = mazo.cartas.map((c, i) => (c.enAbanico ? i : null)).filter((i) => i !== null);
        if (!libres.length) return;
        const indice = libres[azarEntero(libres.length)];
        registrar(`whisper hover { indice: ${indice} }`);
        animaciones.resaltar(indice, 'remoto', true);
        setTimeout(() => animaciones.resaltar(indice, 'remoto', false), 1500);
    },

    reiniciar() {
        registrar('— reinicio —');
        reiniciar();
    },
};

// --- Interacción con el abanico ---

crearInteraccion({
    canvas: escena.canvas,
    camara: escena.camara,
    objetivos: () => (servidor.estado === 'eligiendo' ? mazo.seleccionables() : []),
    alPasar(carta) {
        if (hoverPropio !== null) animaciones.resaltar(hoverPropio, 'propio', false);
        hoverPropio = carta ? mazo.cartas.indexOf(carta) : null;
        if (hoverPropio !== null) animaciones.resaltar(hoverPropio, 'propio', true);
    },
    alTocar(carta) {
        acciones.elegir(mazo.cartas.indexOf(carta));
    },
});

// --- HUD del laboratorio ---

const ETIQUETAS = {
    lista: 'Lista para mezclar',
    eligiendo: 'Eligiendo cartas',
    revelando: 'Revelando',
    finalizada: 'Finalizada',
};

function actualizarBotones() {
    const { estado, elegidas } = servidor;
    const cantidad = tiradas[servidor.tipo].posiciones.length;
    $('#estado').textContent = `${ETIQUETAS[estado]}${estado === 'eligiendo' ? ` ${elegidas.length}/${cantidad}` : ''}`;
    $('[data-accion=mezclar]').disabled = estado !== 'lista';
    $('[data-accion=elegir]').disabled = estado !== 'eligiendo';
    $('[data-accion=remoto]').disabled = estado !== 'eligiendo';
    $('[data-accion=revelar]').disabled = estado !== 'revelando' || elegidas.every((x) => x.revelada);
    $('[data-accion=finalizar]').disabled = estado !== 'revelando' || elegidas.some((x) => !x.revelada);
}

function registrar(texto) {
    const registro = $('#registro');
    registro.textContent = `${texto}\n\n${registro.textContent}`.slice(0, 6000);
}

document.querySelectorAll('[data-accion]').forEach((boton) =>
    boton.addEventListener('click', () => {
        acciones[boton.dataset.accion]();
        actualizarBotones();
    }),
);
$('#tipo').addEventListener('change', () => acciones.reiniciar());

reiniciar();
registrar('Listo. Tocá «Mezclar».');

// Para curiosear desde la consola de DevTools.
window.laboratorio = {
    escena,
    mazo,
    acciones,
    get animaciones() {
        return animaciones;
    },
    get pendientes() {
        return pendientes;
    },
};
