// El "controlador" de la mesa: conecta la red (tiempoReal, api) con la escena
// (animaciones) y el HUD. Es el único módulo que conoce a todos los demás.
//
// Regla de oro: un clic NO mueve nada. Hace un POST; la animación arranca recién
// cuando llega el evento por Reverb, en los dos navegadores a la vez.

import { crearAnimaciones } from './animaciones.js';
import { enviar, pedirEstado } from './api.js';
import { crearCola } from './cola.js';
import { crearEscena, hayWebGL } from './escena.js';
import { crearHud } from './hud.js';
import { crearInteraccion } from './interaccion.js';
import { crearMazo } from './mazo.js';
import { conectar } from './tiempoReal.js';

const config = JSON.parse(document.getElementById('datos-mesa').textContent);
const soyTarotista = config.yo.rol === 'tarotista';
const cantidad = config.posiciones.length;

if (!hayWebGL()) {
    document.getElementById('sin-webgl').hidden = false;
    throw new Error('Sin WebGL 2');
}

const escena = crearEscena(document.getElementById('lienzo'));
const mazo = crearMazo(escena);
const animaciones = crearAnimaciones({ escena, mazo, tipoTirada: config.tipo_tirada });
const hud = crearHud(config, {
    alMezclar: () => accion('mezclar'),
    alRevelarSiguiente: () => {
        const siguiente = [...mesa.cartas.values()].filter((c) => !c.revelada).sort((a, b) => a.posicion - b.posicion)[0];
        if (siguiente) accion('revelar', { posicion: siguiente.posicion });
    },
    alFinalizar: () => accion('finalizar'),
});

// Copia local de lo que dijo el servidor. Solo cambia con GET /estado o con un evento.
const mesa = { estado: null, consultante: null, urlResumen: null, cartas: new Map(), ocupada: false };

const refrescar = () =>
    hud.mostrar({
        estado: mesa.estado,
        elegidas: mesa.cartas.size,
        reveladas: [...mesa.cartas.values()].filter((c) => c.revelada).length,
        consultante: mesa.consultante,
        urlResumen: mesa.urlResumen,
        ocupada: mesa.ocupada,
    });

// --- Cola: la foto de /estado y los eventos se aplican de a uno y en orden ---

const cola = crearCola();
let pendientes = 0;
const encolar = (tarea) => {
    pendientes++;

    return cola(tarea).finally(() => pendientes--);
};

async function cargarEstado() {
    let foto;
    try {
        foto = await pedirEstado(config.rutas.estado);
    } catch (error) {
        hud.aviso(`No se pudo cargar la mesa: ${error.message}`);

        return;
    }

    mesa.estado = foto.estado;
    mesa.consultante = foto.consultante;
    mesa.urlResumen = foto.url_resumen;
    mesa.cartas = new Map(foto.cartas.map((c) => [c.posicion, c]));

    hud.limpiarReveladas();
    foto.cartas.filter((c) => c.revelada).forEach(hud.agregarRevelada);
    refrescar();
    await animaciones.dibujar(foto);
}

// Cada evento primero se fija si ya está reflejado (pudo venir incluido en la foto
// de /estado): así un evento "repetido" no hace nada.
const alEvento = {
    ConsultanteIngreso({ nombre }) {
        mesa.consultante = nombre;
        if (mesa.estado === 'esperando') mesa.estado = 'lista';
        refrescar();
    },

    async MazoMezclado(payload) {
        if (mesa.estado !== 'lista') return;
        mesa.estado = 'eligiendo';
        refrescar();
        await animaciones.mezclar(payload);
        await animaciones.abanico();
    },

    async CartaElegida(payload) {
        if (mesa.cartas.has(payload.posicion)) return;
        mesa.cartas.set(payload.posicion, { ...payload, revelada: false });
        const completa = mesa.cartas.size === cantidad;
        if (completa) mesa.estado = 'revelando'; // el servidor pasa a Revelando en la misma transacción
        refrescar();
        await animaciones.elegir(payload);
        if (completa) await animaciones.recogerAbanico();
    },

    async CartaRevelada(payload) {
        const elegida = mesa.cartas.get(payload.posicion);
        if (!elegida || elegida.revelada) return;
        mesa.cartas.set(payload.posicion, { ...elegida, ...payload, revelada: true }); // conserva indice_abanico
        refrescar();
        await animaciones.revelar(payload);
        hud.agregarRevelada(payload);
    },

    async SesionFinalizada({ url_resumen }) {
        if (mesa.estado === 'finalizada') return;
        mesa.estado = 'finalizada';
        mesa.urlResumen = url_resumen;
        refrescar();
        await animaciones.finalizar();
    },
};

// --- Acciones: POST y nada más ---

async function accion(nombre, datos = {}) {
    if (mesa.ocupada) return; // evita el doble clic; igual el servidor tiene el lock
    mesa.ocupada = true;
    refrescar();
    try {
        await enviar(config.rutas[nombre], datos);
    } catch (error) {
        hud.aviso(error.message);
    } finally {
        mesa.ocupada = false;
        refrescar();
    }
}

// --- Tiempo real ---

let hoverRemoto = null;

const red = conectar(config.uuid, {
    // Primero suscripción, después foto: así no se pierde ningún evento en el medio.
    // here() vuelve a dispararse después de cada reconexión → se redibuja desde /estado.
    alSuscribirse(miembros) {
        hud.presencia(miembros);
        encolar(cargarEstado);
    },
    alEntrar: (miembro) => hud.entro(miembro),
    alSalir: (miembro) => hud.salio(miembro),
    alError: () => hud.aviso('No se pudo entrar a la sala en tiempo real. Recargá la página.'),
    alCambiarConexion: (estado) => hud.conexion(estado),
    alEvento: (nombre, payload) => encolar(() => alEvento[nombre](payload)),

    // Whisper de la consultante: llega directo de su navegador, así que se valida.
    alHoverRemoto({ indice }) {
        if (hoverRemoto !== null) animaciones.resaltar(hoverRemoto, 'remoto', false);
        hoverRemoto = Number.isInteger(indice) && indice >= 0 && indice < mazo.cartas.length ? indice : null;
        if (hoverRemoto !== null) animaciones.resaltar(hoverRemoto, 'remoto', true);
    },
});
hud.conexion(window.Echo.connector.pusher.connection.state);

// --- Mouse / dedo sobre la mesa ---

// Como mucho un whisper cada 80 ms, pero siempre se manda el último valor.
let ultimoEnvio = 0;
let susurroPendiente;
function susurrar(indice) {
    clearTimeout(susurroPendiente);
    const espera = Math.max(0, 80 - (performance.now() - ultimoEnvio));
    susurroPendiente = setTimeout(() => {
        ultimoEnvio = performance.now();
        red.susurrarHover(indice);
    }, espera);
}

let hoverPropio = null;

crearInteraccion({
    canvas: escena.canvas,
    camara: escena.camara,
    objetivos() {
        if (mesa.ocupada) return [];
        if (!soyTarotista && mesa.estado === 'eligiendo' && mesa.cartas.size < cantidad) return mazo.seleccionables();
        if (soyTarotista && mesa.estado === 'revelando') return mazo.sinRevelar();

        return [];
    },
    alPasar(carta) {
        if (soyTarotista) return; // la tarotista ve la mano sobre la tirada; el brillo es para el abanico
        if (hoverPropio !== null) animaciones.resaltar(hoverPropio, 'propio', false);
        hoverPropio = carta ? mazo.cartas.indexOf(carta) : null;
        if (hoverPropio !== null) animaciones.resaltar(hoverPropio, 'propio', true);
        susurrar(hoverPropio);
    },
    alTocar(carta) {
        if (soyTarotista) accion('revelar', { posicion: carta.posicion });
        else accion('elegir', { indice_abanico: mazo.cartas.indexOf(carta) });
    },
});

// Para curiosear desde DevTools (y para las pruebas automáticas).
window.mesaArcana = {
    mesa,
    mazo,
    escena,
    get pendientes() {
        return pendientes;
    },
};
