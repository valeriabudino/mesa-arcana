// El "controlador" de la sala privada: conecta la red (tiempoReal, api) con la escena
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

if (!hayWebGL()) {
    document.getElementById('sin-webgl').hidden = false;
    throw new Error('Sin WebGL 2');
}

const escena = crearEscena(document.getElementById('lienzo'));
const mazo = crearMazo(escena);
const animaciones = crearAnimaciones({ escena, mazo, tipoTirada: 'tres_cartas' }); // el tipo real llega con la lectura
const hud = crearHud(config, {
    alMezclar: () => accion('mezclar'),
    alRevelarSiguiente: () => {
        const siguiente = [...mesa.cartas.values()].filter((c) => !c.revelada).sort((a, b) => a.posicion - b.posicion)[0];
        if (siguiente) accion('revelar', { posicion: siguiente.posicion });
    },
});

// Copia local de lo que dijo el servidor. Solo cambia con GET /estado o con un evento.
// lectura = la lectura activa de la consulta ({ id, tipo, estado, pregunta }) o null.
const mesa = { cargada: false, lectura: null, cartas: new Map(), ocupada: false };

const cantidad = () => config.tipos[mesa.lectura.tipo].posiciones.length;

const refrescar = () =>
    hud.mostrar({
        lectura: mesa.lectura,
        elegidas: mesa.cartas.size,
        reveladas: [...mesa.cartas.values()].filter((c) => c.revelada).length,
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

    const { cartas = [], ...lectura } = foto.lectura ?? {};
    mesa.cargada = true;
    mesa.lectura = foto.lectura ? lectura : null;
    mesa.cartas = new Map(cartas.map((c) => [c.posicion, c]));

    hud.limpiarReveladas();
    cartas.filter((c) => c.revelada).forEach(hud.agregarRevelada);
    refrescar();

    if (foto.lectura) await animaciones.dibujar(foto.lectura);
    else animaciones.reiniciar(); // sin lectura: el mazo quieto sobre la mesa
}

// Cada evento primero se fija si ya está reflejado (pudo venir incluido en la foto
// de /estado): así un evento "repetido" no hace nada.
const alEvento = {
    async MazoMezclado(payload) {
        if (mesa.lectura?.estado !== 'lista') return;
        mesa.lectura.estado = 'eligiendo';
        refrescar();
        await animaciones.mezclar(payload);
        await animaciones.abanico();
    },

    async CartaElegida(payload) {
        if (!mesa.lectura || mesa.cartas.has(payload.posicion)) return;
        mesa.cartas.set(payload.posicion, { ...payload, revelada: false });
        const completa = mesa.cartas.size === cantidad();
        if (completa) mesa.lectura.estado = 'revelando'; // el servidor pasa a Revelando en la misma transacción
        refrescar();
        await animaciones.elegir(payload);
        if (completa) await animaciones.recogerAbanico();
    },

    async CartaRevelada(payload) {
        const elegida = mesa.cartas.get(payload.posicion);
        if (!elegida || elegida.revelada) return;
        mesa.cartas.set(payload.posicion, { ...elegida, ...payload, revelada: true }); // conserva indice_abanico
        if ([...mesa.cartas.values()].every((c) => c.revelada)) mesa.lectura.estado = 'abierta'; // ídem: lo hace el servidor solo
        refrescar();
        await animaciones.revelar(payload);
        hud.agregarRevelada(payload);
    },
};

// --- Acciones: POST y nada más ---

async function accion(nombre, datos = {}) {
    if (mesa.ocupada || !mesa.lectura) return; // evita el doble clic; igual el servidor tiene el lock
    mesa.ocupada = true;
    refrescar();
    try {
        await enviar(`${config.rutas.lecturas}/${mesa.lectura.id}/${nombre}`, datos);
    } catch (error) {
        hud.aviso(error.message);
        // Lo que sea que haya pasado, la pantalla se pone al día con el servidor.
        encolar(cargarEstado);
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
        const estado = mesa.lectura?.estado;
        if (!soyTarotista && estado === 'eligiendo' && mesa.cartas.size < cantidad()) return mazo.seleccionables();
        if (soyTarotista && estado === 'revelando') return mazo.sinRevelar();

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
