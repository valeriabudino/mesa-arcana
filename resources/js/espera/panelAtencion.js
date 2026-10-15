// Panel de atención de la tarotista: la fila en vivo por el canal PRIVADO "tarotistas".
//   TurnoCreado    → aparece una tarjeta (nombre, motivo, hace cuánto)
//   TurnoTomado    → la tarjeta se va con "Lo tomó …"
//   TurnoCancelado → la tarjeta se va con "Salió de la fila"
// "Atender" hace un POST: el lock del servidor decide quién gana si dos tocan a la vez.

import '../echo.js';
import { enviar, pedirEstado } from '../mesa/api.js';

const config = JSON.parse(document.getElementById('datos-atencion').textContent);
const $ = (selector) => document.querySelector(selector);
const turnos = new Map(); // uuid → { uuid, nombre, motivo, desde }
const saliendo = new Map(); // uuid → texto de despedida ("Lo tomó Sofía")
let ocupada = false;
let temporizadorAviso;

function aviso(texto) {
    $('#aviso').textContent = texto;
    $('#aviso').hidden = false;
    clearTimeout(temporizadorAviso);
    temporizadorAviso = setTimeout(() => ($('#aviso').hidden = true), 4000);
}

function haceCuanto(iso) {
    const minutos = Math.floor((Date.now() - new Date(iso)) / 60000);
    if (minutos < 1) return 'recién';

    return minutos < 60 ? `hace ${minutos} min` : `hace ${Math.floor(minutos / 60)} h ${minutos % 60} min`;
}

// Todo con textContent: nombre y motivo los escribió otra persona.
function tarjeta(turno) {
    const li = document.createElement('li');
    li.className = 'flex items-center gap-4 rounded-xl border border-[#C9A54C]/30 bg-[#2A1E4A]/40 p-4 transition-opacity duration-700';

    const datos = document.createElement('div');
    datos.className = 'min-w-0 flex-1';
    const nombre = document.createElement('p');
    nombre.className = 'font-semibold';
    nombre.textContent = turno.nombre;
    const motivo = document.createElement('p');
    motivo.className = 'truncate text-sm text-[#F3EBDD]/70';
    motivo.textContent = turno.motivo ?? 'Sin motivo';
    const desde = document.createElement('p');
    desde.className = 'text-xs text-[#F3EBDD]/50';
    desde.textContent = haceCuanto(turno.desde);
    datos.append(nombre, motivo, desde);
    li.append(datos);

    if (saliendo.has(turno.uuid)) {
        li.classList.add('opacity-40');
        const texto = document.createElement('span');
        texto.className = 'text-sm text-[#C97B8E]';
        texto.textContent = saliendo.get(turno.uuid);
        li.append(texto);
    } else {
        const boton = document.createElement('button');
        boton.className = 'boton';
        boton.textContent = 'Atender';
        boton.disabled = ocupada || !$('#disponible').checked || Boolean(config.consulta_activa);
        boton.title = config.consulta_activa
            ? 'Terminá tu consulta en curso para atender a otra persona'
            : !$('#disponible').checked ? 'Activá «Disponible» para atender' : '';
        boton.addEventListener('click', () => atender(turno.uuid));
        li.append(boton);
    }

    return li;
}

function dibujar() {
    $('#cola').replaceChildren(...[...turnos.values()].map(tarjeta));
    $('#cola-vacia').hidden = turnos.size > 0;
    $('#aviso-consulta-activa').hidden = !config.consulta_activa;
    if (config.consulta_activa) $('#consulta-activa').href = config.consulta_activa;
}

/** La tarjeta muestra por qué se va, y desaparece al rato. */
function despedir(uuid, texto) {
    if (!turnos.has(uuid)) return;
    saliendo.set(uuid, texto);
    dibujar();
    setTimeout(() => {
        turnos.delete(uuid);
        saliendo.delete(uuid);
        dibujar();
    }, 2500);
}

async function sincronizar() {
    try {
        const cola = await pedirEstado(config.rutas.cola);
        turnos.clear();
        cola.forEach((t) => turnos.set(t.uuid, t));
        dibujar();
    } catch (error) {
        aviso(`No se pudo cargar la fila: ${error.message}`);
    }
}

async function atender(uuid) {
    if (ocupada) return;
    ocupada = true;
    dibujar();
    try {
        const { url } = await enviar(`${config.rutas.tomar}/${uuid}/tomar`);
        window.location.href = url;
    } catch (error) {
        aviso(error.message); // 409 "Lo tomó Sofía", "Activá «Disponible»…"
        ocupada = false;
        sincronizar();
    }
}

$('#disponible').addEventListener('change', async (e) => {
    const valor = e.target.checked;
    dibujar();
    try {
        await enviar(config.rutas.disponible, { disponible: valor });
    } catch (error) {
        e.target.checked = !valor;
        dibujar();
        aviso(error.message);
    }
});

window.Echo.private('tarotistas')
    .subscribed(sincronizar) // al entrar y en cada reconexión: la foto completa
    .listen('.TurnoCreado', (turno) => {
        turnos.set(turno.uuid, turno);
        dibujar();
    })
    .listen('.TurnoActualizado', (turno) => {
        if (!turnos.has(turno.uuid)) return;
        turnos.set(turno.uuid, turno); // cambió el motivo
        dibujar();
    })
    .listen('.TurnoTomado', ({ uuid, tarotista }) => {
        if (tarotista !== config.yo) despedir(uuid, `Lo tomó ${tarotista}`);
    })
    .listen('.TurnoCancelado', ({ uuid }) => despedir(uuid, 'Salió de la fila'));

const conexion = window.Echo.connector.pusher.connection;
const banner = (estado) => {
    $('#conexion').hidden = estado === 'connected';
    $('#conexion').textContent = estado === 'connecting' || estado === 'initialized' ? 'Conectando…' : 'Reconectando…';
};
conexion.bind('state_change', ({ current }) => banner(current));
banner(conexion.state);

setInterval(dibujar, 30000); // refresca los "hace X min"
dibujar();
