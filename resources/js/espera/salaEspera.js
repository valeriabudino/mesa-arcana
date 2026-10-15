// Sala de espera de la consultante.
//   · canal PÚBLICO sala-espera: ColaActualizada trae solo la fila de uuids anónimos;
//     busco el mío y calculo cuántas hay antes.
//   · canal PRIVADO App.Models.User.{id}: TuTarotistaTeEspera, el aviso personal.
// Misma receta que la mesa: primero suscribirse, después pedir la foto (/espera/estado),
// y repetirla en cada (re)suscripción, así no se pierde nada en el medio.

import '../echo.js';
import { pedirEstado } from '../mesa/api.js';

const config = JSON.parse(document.getElementById('datos-espera').textContent);
const $ = (selector) => document.querySelector(selector);
let llamada = false;

function mostrarPosicion(cola) {
    const antes = cola.indexOf(config.turno);
    if (antes === -1) return false; // ya no estoy esperando (me atendieron o salí)

    $('#posicion').textContent =
        antes === 0 ? 'Sos la próxima ✦' : `Hay ${antes} persona${antes === 1 ? '' : 's'} antes que vos`;

    return true;
}

function irALaConsulta(tarotista, url) {
    if (llamada) return;
    llamada = true;
    $('#llamado-tarotista').textContent = tarotista;
    $('#llamado-link').href = url;
    $('#llamado').hidden = false;
    $('#salir').hidden = true; // ya la atendieron: salir de la fila no tiene sentido (daría 409)
    $('#posicion').textContent = 'Es tu turno';
    setTimeout(() => (window.location.href = url), 3000);
}

async function sincronizar() {
    try {
        const estado = await pedirEstado(config.rutas.estado);
        if (estado.url_consulta) return irALaConsulta('Tu tarotista', estado.url_consulta);
        // Mi turno ya no espera (por ejemplo, salí desde otra pestaña): la página decide qué mostrar.
        if (!mostrarPosicion(estado.cola)) window.location.reload();
    } catch {
        $('#posicion').textContent = 'No se pudo cargar la fila. Recargá la página.';
    }
}

window.Echo.channel('sala-espera')
    .subscribed(sincronizar)
    .listen('.ColaActualizada', ({ turnos }) => {
        if (!mostrarPosicion(turnos)) sincronizar();
    });

window.Echo.private(`App.Models.User.${config.yo}`)
    .subscribed(sincronizar)
    .listen('.TuTarotistaTeEspera', ({ tarotista, url }) => irALaConsulta(tarotista, url));

const conexion = window.Echo.connector.pusher.connection;
const banner = (estado) => {
    $('#conexion').hidden = estado === 'connected';
    $('#conexion').textContent = estado === 'connecting' || estado === 'initialized' ? 'Conectando…' : 'Reconectando…';
};
conexion.bind('state_change', ({ current }) => banner(current));
banner(conexion.state);
