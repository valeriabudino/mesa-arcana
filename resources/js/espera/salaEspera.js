// Sala de espera de la consultante.
//   · canal PÚBLICO sala-espera: ColaActualizada trae solo la fila de uuids anónimos;
//     busco el mío y calculo cuántas hay antes.
//   · canal PRIVADO App.Models.User.{id}: TuTarotistaTeEspera, el aviso personal.
// Misma receta que la mesa: primero suscribirse, después pedir la foto (/espera/estado),
// y repetirla en cada (re)suscripción, así no se pierde nada en el medio.

import '../echo.js';
import '../sonido.js';
import { crearFondo } from '../inicio/fondo3d.js';
import { enviar, pedirEstado } from '../mesa/api.js';

const config = JSON.parse(document.getElementById('datos-espera').textContent);
const $ = (selector) => document.querySelector(selector);
let llamada = false;
let antesPrevio = null;

crearFondo(document.getElementById('fondo'), { modo: 'mazo' });

/** Un "ding" suave con WebAudio (sin archivos). Si el navegador no deja sonar, no pasa nada. */
function campanita() {
    try {
        const audio = new AudioContext();
        for (const [frecuencia, demora] of [[880, 0], [1320, 0.12]]) {
            const oscilador = audio.createOscillator();
            const volumen = audio.createGain();
            oscilador.frequency.value = frecuencia;
            volumen.gain.setValueAtTime(0.0001, audio.currentTime + demora);
            volumen.gain.exponentialRampToValueAtTime(0.15, audio.currentTime + demora + 0.02);
            volumen.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + demora + 1.6);
            oscilador.connect(volumen).connect(audio.destination);
            oscilador.start(audio.currentTime + demora);
            oscilador.stop(audio.currentTime + demora + 1.7);
        }
    } catch {
        // sin sonido
    }
}

const latir = () => {
    $('#posicion').classList.add('scale-110', 'text-dorado');
    setTimeout(() => $('#posicion').classList.remove('scale-110', 'text-dorado'), 600);
};

function mostrarPosicion(cola) {
    const antes = cola.indexOf(config.turno);
    if (antes === -1) return false; // ya no estoy esperando (me atendieron o salí)

    $('#posicion').textContent =
        antes === 0 ? 'Tu turno es el próximo ✦' : `Hay ${antes} persona${antes === 1 ? '' : 's'} antes que vos`;
    if (antesPrevio !== null && antes !== antesPrevio) latir(); // el número "late" cuando cambia
    antesPrevio = antes;

    return true;
}

function irALaConsulta(tarotista, url) {
    if (llamada) return;
    llamada = true;
    $('#llamado-tarotista').textContent = tarotista;
    $('#llamado-link').href = url;
    $('#llamado').hidden = false;
    $('#form-motivo').hidden = true;
    document.body.classList.add('transition-colors', 'duration-1000', '!bg-violeta'); // la pantalla se ilumina
    campanita();
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

// El motivo se puede cambiar mientras se espera: la tarotista lo ve al instante.
$('#form-motivo').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await enviar(config.rutas_turno.motivo, { motivo: $('#motivo').value.trim() || null });
        $('#aviso').textContent = 'Motivo guardado.';
    } catch (error) {
        $('#aviso').textContent = error.message;
    }
    $('#aviso').hidden = false;
    setTimeout(() => ($('#aviso').hidden = true), 2500);
});

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
