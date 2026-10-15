// El HTML encima del canvas: presencia, estado, barra de acción y panel de reveladas.
// No conoce Echo ni Three.js: mesa.js le dice qué mostrar.

const $ = (selector) => document.querySelector(selector);

function boton(texto, alTocar) {
    const b = document.createElement('button');
    b.className = 'boton';
    b.textContent = texto;
    b.addEventListener('click', alTocar);

    return b;
}

function texto(contenido, clase = 'text-[#F3EBDD]/80') {
    const p = document.createElement('p');
    p.className = clase;
    p.textContent = contenido;

    return p;
}

export function crearHud(config, acciones) {
    const soyTarotista = config.yo.rol === 'tarotista';
    const miembros = new Map(); // id → { id, name, rol }, lo que devuelve routes/channels.php
    let temporizadorAviso;

    const pintarPresencia = () => {
        const roles = new Set([...miembros.values()].map((m) => m.rol));
        for (const rol of ['tarotista', 'consultante']) {
            $(`[data-presencia=${rol}] i`).className = `size-2.5 rounded-full ${roles.has(rol) ? 'bg-emerald-400' : 'bg-zinc-600'}`;
        }
    };

    const hud = {
        /** Quiénes están conectadas ahora (presencia): here() trae la lista, joining/leaving los cambios. */
        presencia(lista) {
            miembros.clear();
            lista.forEach((m) => miembros.set(m.id, m));
            pintarPresencia();
        },

        entro(miembro) {
            miembros.set(miembro.id, miembro);
            pintarPresencia();
        },

        salio(miembro) {
            miembros.delete(miembro.id);
            pintarPresencia();
        },

        conexion(estado) {
            $('#conexion').hidden = estado === 'connected';
            $('#conexion').textContent = estado === 'connecting' || estado === 'initialized' ? 'Conectando…' : 'Reconectando…';
        },

        aviso(mensaje) {
            $('#aviso').textContent = mensaje;
            $('#aviso').hidden = false;
            clearTimeout(temporizadorAviso);
            temporizadorAviso = setTimeout(() => ($('#aviso').hidden = true), 4000);
        },

        /** Estado de la lectura activa + barra de acción: cada una ve solo lo que puede hacer ahora. */
        mostrar({ lectura, elegidas, reveladas, ocupada }) {
            const barra = $('#acciones');
            barra.replaceChildren();
            const agregar = (...elementos) => barra.append(...elementos);

            $('#pregunta').hidden = !lectura?.pregunta;
            $('#pregunta').textContent = lectura?.pregunta ? `«${lectura.pregunta}»` : '';

            if (!lectura) {
                $('#tipo-lectura').textContent = '';
                $('#estado').textContent = 'Sin lectura';
                agregar(texto('Todavía no hay una lectura en curso.'));

                return;
            }

            const { estado } = lectura;
            const cantidad = config.tipos[lectura.tipo].posiciones.length;
            $('#tipo-lectura').textContent = config.tipos[lectura.tipo].nombre;
            $('#estado').textContent = config.etiquetas[estado] + (estado === 'eligiendo' ? ` ${elegidas}/${cantidad}` : '');

            if (soyTarotista) {
                if (estado === 'lista') agregar(boton('Mezclar', acciones.alMezclar));
                if (estado === 'eligiendo') agregar(texto(`${config.consultante} está eligiendo sus cartas…`));
                if (estado === 'revelando') {
                    agregar(texto('Tocá una carta de la tirada para revelarla, o:'), boton('Revelar la siguiente', acciones.alRevelarSiguiente));
                }
                if (estado === 'abierta') agregar(texto('Están todas reveladas.'));
            } else {
                if (estado === 'lista') agregar(texto(`${config.tarotista} va a mezclar el mazo.`));
                if (estado === 'eligiendo') agregar(texto(`Elegí ${cantidad - elegidas} carta${cantidad - elegidas === 1 ? '' : 's'} del abanico.`));
                if (estado === 'revelando') agregar(texto(`${config.tarotista} está revelando tus cartas…`));
                if (estado === 'abierta') agregar(texto(`Ya se revelaron ${reveladas} cartas.`));
            }

            barra.querySelectorAll('button').forEach((b) => (b.disabled = ocupada));
        },

        /** Agrega una carta revelada al panel lateral (payload de CartaRevelada). */
        agregarRevelada({ posicion, nombre_posicion: lugar, invertida, carta }) {
            $('#sin-reveladas').hidden = true;
            const item = document.createElement('li');
            item.dataset.posicion = posicion;
            item.append(
                texto(lugar.toUpperCase(), 'text-xs tracking-widest text-[#C9A54C]'),
                texto(carta.nombre, 'text-base font-semibold'),
                texto(invertida ? 'Invertida' : 'Al derecho', 'text-xs text-[#C97B8E]'),
                texto(carta.significado),
                texto(carta.palabras_clave.join(' · '), 'text-xs text-[#F3EBDD]/50'),
            );
            // En orden de posición, aunque se revelen salteadas.
            const siguiente = [...$('#reveladas').children].find((li) => Number(li.dataset.posicion) > posicion);
            $('#reveladas').insertBefore(item, siguiente ?? null);
        },

        limpiarReveladas() {
            $('#reveladas').replaceChildren();
            $('#sin-reveladas').hidden = false;
        },
    };

    return hud;
}
