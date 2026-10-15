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
        const consultante = [...miembros.values()].find((m) => m.rol === 'consultante');
        if (consultante) hud.nombreConsultante(consultante.name);
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

        nombreConsultante(nombre) {
            if (nombre) $('[data-presencia=consultante] [data-nombre]').textContent = nombre;
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

        /** Estado + barra de acción: cada una ve solo lo que puede hacer en este momento. */
        mostrar({ estado, elegidas, reveladas, consultante, urlResumen, ocupada }) {
            const cantidad = config.posiciones.length;
            $('#estado').textContent = config.etiquetas[estado] + (estado === 'eligiendo' ? ` ${elegidas}/${cantidad}` : '');
            hud.nombreConsultante(consultante);

            const barra = $('#acciones');
            barra.replaceChildren();
            const agregar = (...elementos) => barra.append(...elementos);

            if (estado === 'finalizada') {
                const link = document.createElement('a');
                link.className = 'boton';
                link.href = urlResumen;
                link.textContent = 'Ver el resumen';
                agregar(texto('La lectura terminó.'), link);

                return;
            }

            if (soyTarotista) {
                if (estado === 'esperando') {
                    const campo = document.createElement('input');
                    campo.readOnly = true;
                    campo.value = config.link;
                    campo.className = 'w-80 max-w-full rounded-md border border-[#C9A54C]/40 bg-[#2A1E4A] px-2 py-1.5';
                    campo.addEventListener('focus', () => campo.select());
                    agregar(
                        texto('Pasale este link a la consultante:'),
                        campo,
                        boton('Copiar', async () => {
                            campo.select();
                            await navigator.clipboard?.writeText(config.link).catch(() => {});
                            hud.aviso('Link copiado');
                        }),
                    );
                }
                if (estado === 'lista') agregar(boton('Mezclar', acciones.alMezclar));
                if (estado === 'eligiendo') agregar(texto(`${consultante ?? 'La consultante'} está eligiendo sus cartas…`));
                if (estado === 'revelando') {
                    agregar(
                        reveladas < cantidad
                            ? texto('Tocá una carta de la tirada para revelarla, o:')
                            : texto('Ya están todas reveladas.'),
                    );
                    if (reveladas < cantidad) agregar(boton('Revelar la siguiente', acciones.alRevelarSiguiente));
                    else agregar(boton('Finalizar la lectura', acciones.alFinalizar));
                }
            } else {
                if (estado === 'lista') agregar(texto(`${config.tarotista} va a mezclar el mazo.`));
                if (estado === 'eligiendo') agregar(texto(`Elegí ${cantidad - elegidas} carta${cantidad - elegidas === 1 ? '' : 's'} del abanico.`));
                if (estado === 'revelando') agregar(texto(`${config.tarotista} está revelando tus cartas…`));
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
