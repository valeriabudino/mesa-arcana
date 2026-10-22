// El "controlador" de la sala privada: conecta la red (tiempoReal, api) con la escena
// (animaciones), el HUD y el chat. Es el único módulo que conoce a todos los demás.
//
// Regla de oro: un clic NO mueve nada. Hace un POST; lo que cambia en pantalla llega
// después, por Reverb, en los dos navegadores a la vez (también los mensajes del chat).

import { crearAnimaciones } from './animaciones.js';
import { crearBorrador } from './borrador.js';
import { enviar, pedirEstado } from './api.js';
import { crearChat } from './chat.js';
import { crearCola } from './cola.js';
import { crearEscena, hayWebGL } from './escena.js';
import { crearHud } from './hud.js';
import { crearInteraccion } from './interaccion.js';
import { crearMazo } from './mazo.js';
import { conectar } from './tiempoReal.js';
import '../sonido.js'; // monta los botones de música y efectos

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

const cantidad = () => mesa.lectura.cartas_esperadas; // la tirada + las aclaratorias aceptadas
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
    alPedir: (servicio, preguntaId) => accion(config.rutas.pedidos, { servicio, pregunta_id: preguntaId }),
    alPedirAclaratoria: (posicion) => accion(urlLectura('aclaratoria'), { posicion }),
    alCancelarPedido: () => {
        const pedido = chat.pedidoPendiente();
        if (pedido !== null) accion(`${config.rutas.pedidos}/${pedido}/cancelar`);
    },
    alEnviarNacimiento: (datos) => accion(urlLectura('nacimiento'), datos),
    alSenalar: (planeta) => accion(urlLectura('senalar'), { planeta }),
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
    // Para mostrar "Pidió aclarar «Presente»": la posición se nombra con la lectura en curso.
    nombrePosicion: (posicion) => (mesa.lectura ? config.tipos[mesa.lectura.tipo].posiciones[posicion] : null),
    alEscribir: () => red?.susurrarEscribiendo(),
    // Los datos de nacimiento no viajan con el mensaje de la carta astral: se piden a /estado.
    nacimientoDe: (lectura) => (mesa.lectura?.id === lectura ? mesa.lectura.nacimiento ?? null : null),
});

// El borrador de la interpretación (solo existe en la pantalla de la tarotista).
const borrador = crearBorrador({
    alPublicar: (lectura, texto) => accion(`${config.rutas.lecturas}/${lectura}/interpretacion`, { texto }),
    alMostrar: () => chat.plegar(false), // en el celu, el chat se abre para que lo vea
});
const privado = window.Echo.private(`App.Models.User.${config.yo.id}`);
if (soyTarotista) {
    privado.listen('.BorradorInterpretacion', (datos) => {
        if (datos.consulta === config.uuid) borrador.mostrar(datos);
    });
}
// La billetera (plan v3 §13.2): el saldo cambia al reservar, cobrar o liberar.
privado.listen('.SaldoActualizado', (saldo) => {
    hud.mostrarSaldo(saldo);
    refrescar();
});

function refrescar() {
    hud.mostrar({
        estadoConsulta: mesa.estadoConsulta,
        urlResumen: mesa.urlResumen,
        lectura: mesa.lectura,
        cartas: [...mesa.cartas.values()],
        elegidas: mesa.cartas.size,
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

    chat.habilitar(mesa.estadoConsulta === 'activa');
    if (foto.borrador) borrador.mostrar(foto.borrador);
    if (foto.saldo) hud.mostrarSaldo(foto.saldo); // por si se perdió un SaldoActualizado mientras no estaba
    refrescar();

    if (lectura.tipo === 'carta_astral') {
        chat.mostrarNacimiento(lectura.id, lectura.nacimiento);
        if (lectura.resultado) animaciones.dibujarCielo(lectura.resultado);
        else animaciones.reiniciar();
    } else if (foto.lectura) {
        await animaciones.dibujar(foto.lectura);
    } else {
        animaciones.reiniciar(); // sin lectura: el mazo quieto sobre la mesa
    }
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
        borrador.ocultar(); // el de la lectura anterior ya no se publica
        animaciones.reiniciar();
        if (lectura.tipo !== 'carta_astral') animaciones.usarTirada(lectura.tipo);
        refrescar();
    },

    // --- Carta astral ---

    async DatosNacimientoRecibidos(payload) {
        if (!esDeLaLectura(payload)) return;
        mesa.lectura.calculando = true; // el servidor ya está calculando: el HUD lo dice
        refrescar();
    },

    async CartaAstralCalculada({ lectura, resultado }) {
        if (mesa.lectura?.id !== lectura || mesa.lectura.estado === 'calculada') return;
        Object.assign(mesa.lectura, { estado: 'calculada', resultado, calculando: false });
        // Los datos de nacimiento no viajan por el canal (son personales): se piden a /estado,
        // que solo responde a las dos participantes.
        pedirEstado(config.rutas.estado)
            .then((foto) => {
                if (foto.lectura?.id !== lectura || mesa.lectura?.id !== lectura) return;
                mesa.lectura.nacimiento = foto.lectura.nacimiento;
                chat.mostrarNacimiento(lectura, foto.lectura.nacimiento);
            })
            .catch(() => {});
        refrescar();
        await animaciones.entrarAlCielo(resultado);
    },

    async PlanetaSenalado({ lectura, planeta }) {
        if (mesa.lectura?.id !== lectura || mesa.lectura.estado !== 'calculada') return;
        chat.resaltarPunto(planeta);
        await animaciones.senalarPlaneta(planeta);
    },

    async EstadoCambiado({ lectura, estado }) {
        if (mesa.lectura?.id !== lectura || estado !== 'cerrada') return;
        mesa.lectura = null;
        mesa.cartas = new Map();
        refrescar();
        await animaciones.volverAReposo(); // el mazo vuelve a la pila: listo para la próxima
    },

    async AclaratoriaAceptada(payload) {
        if (!esDeLaLectura(payload) || mesa.lectura.estado !== 'abierta') return;
        mesa.lectura.estado = 'eligiendo';
        mesa.lectura.cartas_esperadas++;
        refrescar();
        await animaciones.reabrirAbanico();
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
        await animaciones.revelar(payload); // su mensaje en el chat llega detrás, por la cola
    },
};

// El chat no espera a las animaciones: se aplica en el momento.
const alEventoDelChat = {
    MensajeEnviado(mensaje) {
        // Salvo las cartas: esas esperan su turno en la cola, para aparecer en el chat
        // recién cuando terminaron de girar en la mesa (y no arruinar la sorpresa).
        const mostrar = () => {
            chat.agregar(mensaje);
            refrescar();
        };
        if (mensaje.tipo === 'carta' || mensaje.tipo === 'carta_astral') encolar(mostrar);
        else mostrar();
    },
    InterpretacionPublicada(mensaje) {
        if (borrador.de() === mensaje.lectura) borrador.ocultar();
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
