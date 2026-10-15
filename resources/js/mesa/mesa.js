// El "controlador" de la sala privada: conecta la red (tiempoReal, api) con la escena
// (animaciones), el HUD y el chat. Es el único módulo que conoce a todos los demás.
//
// Regla de oro: un clic NO mueve nada. Hace un POST; lo que cambia en pantalla llega
// después, por Reverb, en los dos navegadores a la vez (también los mensajes del chat).

import { crearAnimaciones } from './animaciones.js';
import { enviar, pedirEstado } from './api.js';
import { crearChat } from './chat.js';
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

// Copia local de lo que dijo el servidor. Solo cambia con GET /estado o con un evento.
// lectura = la lectura activa ({ id, tipo, estado, pregunta }) o null.
const mesa = { cargada: false, estadoConsulta: 'activa', urlResumen: null, lectura: null, cartas: new Map(), ocupada: false };

const cantidad = () => config.tipos[mesa.lectura.tipo].posiciones.length;
const esDeLaLectura = (payload) => mesa.lectura !== null && payload.lectura === mesa.lectura.id;

// --- Acciones: POST y nada más ---

async function accion(url, datos = {}) {
    if (mesa.ocupada) return false; // evita el doble clic; igual el servidor tiene los locks
    mesa.ocupada = true;
    refrescar();
    try {
        await enviar(url, datos);

        return true;
    } catch (error) {
        hud.aviso(error.message);
        // Lo que sea que haya pasado, la pantalla se pone al día con el servidor.
        encolar(cargarEstado);

        return false;
    } finally {
        mesa.ocupada = false;
        refrescar();
    }
}

const urlLectura = (nombre) => `${config.rutas.lecturas}/${mesa.lectura.id}/${nombre}`;

const hud = crearHud(config, {
    alMezclar: () => accion(urlLectura('mezclar')),
    alRevelarSiguiente: () => {
        const siguiente = [...mesa.cartas.values()].filter((c) => !c.revelada).sort((a, b) => a.posicion - b.posicion)[0];
        if (siguiente) accion(urlLectura('revelar'), { posicion: siguiente.posicion });
    },
    alCerrarLectura: () => accion(urlLectura('cerrar')),
    alPedir: (servicio, pregunta) => accion(config.rutas.pedidos, { servicio, pregunta: pregunta || null }),
    alFinalizar: () => window.confirm('¿Terminar la consulta?') && accion(config.rutas.finalizar),
    alAusente: () => accion(config.rutas.ausente),
});

let red; // se asigna más abajo (conectar)

const chat = crearChat(config, {
    // El mensaje no se dibuja acá: aparece cuando vuelve por Reverb.
    alEnviar: (texto) => enviar(config.rutas.mensajes, { contenido: texto }).then(
        () => true,
        (error) => (hud.aviso(error.message), false),
    ),
    alResponderPedido: (id, aceptar) => accion(`${config.rutas.pedidos}/${id}/${aceptar ? 'aceptar' : 'rechazar'}`),
    alEscribir: () => red?.susurrarEscribiendo(),
});

function refrescar() {
    hud.mostrar({
        estadoConsulta: mesa.estadoConsulta,
        urlResumen: mesa.urlResumen,
        lectura: mesa.lectura,
        elegidas: mesa.cartas.size,
        reveladas: [...mesa.cartas.values()].filter((c) => c.revelada).length,
        pedidoPendiente: chat.hayPedidoPendiente(),
        ocupada: mesa.ocupada,
    });
}

// --- Cola: la foto de /estado y los eventos de la mesa se aplican de a uno y en orden ---

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
    mesa.estadoConsulta = foto.estado;
    mesa.urlResumen = foto.url_resumen;
    mesa.lectura = foto.lectura ? lectura : null;
    mesa.cartas = new Map(cartas.map((c) => [c.posicion, c]));

    hud.limpiarReveladas();
    cartas.filter((c) => c.revelada).forEach(hud.agregarRevelada);
    chat.habilitar(mesa.estadoConsulta === 'activa');
    refrescar();

    if (foto.lectura) await animaciones.dibujar(foto.lectura);
    else animaciones.reiniciar(); // sin lectura: el mazo quieto sobre la mesa
}

/** Los mensajes que faltan (todos al entrar; al reconectar, solo los posteriores al último visto). */
async function cargarMensajes() {
    try {
        const nuevos = await pedirEstado(`${config.rutas.mensajes}?despues=${chat.ultimoId}`);
        nuevos.forEach(chat.agregar);
        refrescar();
    } catch (error) {
        hud.aviso(`No se pudo cargar el chat: ${error.message}`);
    }
}

// Cada evento primero se fija si ya está reflejado (pudo venir incluido en la foto
// de /estado) o si es de otra lectura: así un evento repetido o atrasado no hace nada.
const alEventoDeLaMesa = {
    async LecturaIniciada({ lectura }) {
        if (mesa.lectura?.id === lectura.id) return;
        mesa.lectura = lectura;
        mesa.cartas = new Map();
        hud.limpiarReveladas();
        animaciones.reiniciar();
        animaciones.usarTirada(lectura.tipo);
        chat.mostrarPestana('lectura');
        refrescar();
    },

    async EstadoCambiado({ lectura, estado }) {
        if (mesa.lectura?.id !== lectura || estado !== 'cerrada') return;
        mesa.lectura = null;
        mesa.cartas = new Map();
        animaciones.reiniciar(); // vuelve el mazo quieto: lista para la próxima
        refrescar();
    },

    async ConsultaFinalizada({ url_resumen }) {
        if (mesa.estadoConsulta === 'finalizada') return;
        mesa.estadoConsulta = 'finalizada';
        mesa.urlResumen = url_resumen;
        mesa.lectura = null;
        chat.habilitar(false);
        refrescar();
        await animaciones.finalizar();
    },

    async MazoMezclado(payload) {
        if (!esDeLaLectura(payload) || mesa.lectura.estado !== 'lista') return;
        mesa.lectura.estado = 'eligiendo';
        refrescar();
        await animaciones.mezclar(payload);
        await animaciones.abanico();
    },

    async CartaElegida(payload) {
        if (!esDeLaLectura(payload) || mesa.cartas.has(payload.posicion)) return;
        mesa.cartas.set(payload.posicion, { ...payload, revelada: false });
        const completa = mesa.cartas.size === cantidad();
        if (completa) mesa.lectura.estado = 'revelando'; // el servidor pasa a Revelando en la misma transacción
        refrescar();
        await animaciones.elegir(payload);
        if (completa) await animaciones.recogerAbanico();
    },

    async CartaRevelada(payload) {
        const elegida = mesa.cartas.get(payload.posicion);
        if (!esDeLaLectura(payload) || !elegida || elegida.revelada) return;
        mesa.cartas.set(payload.posicion, { ...elegida, ...payload, revelada: true }); // conserva indice_abanico
        if ([...mesa.cartas.values()].every((c) => c.revelada)) mesa.lectura.estado = 'abierta'; // ídem: lo hace el servidor solo
        refrescar();
        await animaciones.revelar(payload);
        hud.agregarRevelada(payload);
    },
};

// El chat no espera a las animaciones: se aplica en el momento.
const alEventoDelChat = {
    MensajeEnviado(mensaje) {
        chat.agregar(mensaje);
        refrescar();
    },
    PedidoResuelto(datos) {
        chat.resolverPedido(datos);
        refrescar();
    },
};

// --- Tiempo real ---

let hoverRemoto = null;

red = conectar(config.uuid, {
    // Primero suscripción, después foto: así no se pierde ningún evento en el medio.
    // here() vuelve a dispararse después de cada reconexión → se redibuja y se piden los mensajes que faltan.
    alSuscribirse(miembros) {
        hud.presencia(miembros);
        encolar(cargarEstado);
        cargarMensajes();
    },
    alEntrar: (miembro) => {
        hud.entro(miembro);
        refrescar();
    },
    alSalir: (miembro) => {
        hud.salio(miembro);
        refrescar();
    },
    alError: () => hud.aviso('No se pudo entrar a la sala en tiempo real. Recargá la página.'),
    alCambiarConexion: (estado) => hud.conexion(estado),
    alEvento(nombre, payload) {
        if (alEventoDelChat[nombre]) alEventoDelChat[nombre](payload);
        else encolar(() => alEventoDeLaMesa[nombre](payload));
    },

    // Whispers: llegan directo del navegador de la otra persona, así que se validan.
    alHoverRemoto({ indice }) {
        if (hoverRemoto !== null) animaciones.resaltar(hoverRemoto, 'remoto', false);
        hoverRemoto = Number.isInteger(indice) && indice >= 0 && indice < mazo.cartas.length ? indice : null;
        if (hoverRemoto !== null) animaciones.resaltar(hoverRemoto, 'remoto', true);
    },
    alEscribiendoRemoto: () => chat.escribiendo(soyTarotista ? config.consultante : config.tarotista),
});
hud.conexion(window.Echo.connector.pusher.connection.state);
setInterval(refrescar, 15000); // por ejemplo, para que aparezca "No llegó" cuando corresponde

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
        if (soyTarotista) accion(urlLectura('revelar'), { posicion: carta.posicion });
        else accion(urlLectura('elegir'), { indice_abanico: mazo.cartas.indexOf(carta) });
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
