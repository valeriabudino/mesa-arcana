// El HTML encima del canvas: presencia, estado, barra de acción y panel de reveladas.
// No conoce Echo ni Three.js: mesa.js le dice qué mostrar.

const $ = (selector) => document.querySelector(selector);

function boton(texto, alTocar, secundario = false) {
    const b = document.createElement('button');
    b.className = secundario ? 'boton !bg-transparent' : 'boton';
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

function enlace(contenido, href, secundario = false) {
    const a = document.createElement('a');
    a.className = secundario ? 'boton !bg-transparent' : 'boton';
    a.href = href;
    a.textContent = contenido;

    return a;
}

const desactivar = (barra, ocupada) => barra.querySelectorAll('button, select, input').forEach((el) => (el.disabled = ocupada));

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

    // "No llegó": la consultante no está conectada y ya pasaron los minutos de espera.
    const puedeMarcarAusente = () =>
        !hud.estaConectada('consultante') && Date.now() - new Date(config.tomado_at) >= config.minutos_ausente * 60000;

    /** La consultante pide un servicio: tipo + pregunta opcional. */
    function formularioPedido() {
        const tipo = document.createElement('select');
        tipo.className = 'campo-chico';
        for (const [valor, { nombre, disponible }] of Object.entries(config.tipos)) {
            const opcion = new Option(disponible ? nombre : `${nombre} (pronto)`, valor);
            opcion.disabled = !disponible;
            tipo.append(opcion);
        }
        const pregunta = document.createElement('input');
        pregunta.className = 'campo-chico w-64 max-w-full';
        pregunta.maxLength = 500;
        pregunta.placeholder = 'Tu pregunta (opcional)';

        return [
            texto('Pedí una lectura:'),
            tipo,
            pregunta,
            boton('Pedir', () => acciones.alPedir(tipo.value, pregunta.value.trim())),
        ];
    }

    /** La consultante pide aclarar una de las cartas reveladas (que todavía no tenga aclaratoria). */
    function formularioAclaratoria(lectura, aclarables) {
        const carta = document.createElement('select');
        carta.className = 'campo-chico';
        for (const c of aclarables) carta.append(new Option(config.tipos[lectura.tipo].posiciones[c.posicion], c.posicion));
        const pregunta = document.createElement('input');
        pregunta.className = 'campo-chico w-56 max-w-full';
        pregunta.maxLength = 500;
        pregunta.placeholder = '¿Qué querés aclarar? (opcional)';

        return [
            texto('¿Te queda una duda?'),
            carta,
            pregunta,
            boton('Pedir aclaratoria', () => acciones.alPedirAclaratoria(Number(carta.value), pregunta.value.trim())),
        ];
    }

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

        /** ¿Está conectada ahora? (presencia) */
        estaConectada(rol) {
            return [...miembros.values()].some((m) => m.rol === rol);
        },

        /** Estado + barra de acción: cada una ve solo lo que puede hacer en este momento. */
        mostrar({ estadoConsulta, urlResumen, lectura, cartas, elegidas, reveladas, pedidoPendiente, ocupada }) {
            const barra = $('#acciones');
            barra.replaceChildren();
            const agregar = (...elementos) => barra.append(...elementos);

            $('#pregunta').hidden = !lectura?.pregunta;
            $('#pregunta').textContent = lectura?.pregunta ? `«${lectura.pregunta}»` : '';
            $('#tipo-lectura').textContent = lectura ? config.tipos[lectura.tipo].nombre : '';

            // --- La consulta terminó ---
            if (estadoConsulta === 'finalizada') {
                $('#estado').textContent = 'Consulta finalizada';
                agregar(texto('La consulta terminó.'), enlace('Ver el resumen', urlResumen));
                agregar(soyTarotista ? enlace('Volver al panel', config.rutas.atencion, true) : enlace('Pedir otra consulta', config.rutas.espera, true));

                return;
            }

            // --- Sin lectura: se conversa y se pide un servicio ---
            if (!lectura) {
                $('#estado').textContent = 'Conversando';
                if (soyTarotista) {
                    agregar(texto(pedidoPendiente ? 'Tenés un pedido para responder en el chat.' : `Esperando que ${config.consultante} pida una lectura.`));
                    if (puedeMarcarAusente()) agregar(boton('No llegó', acciones.alAusente));
                    agregar(boton('Finalizar consulta', acciones.alFinalizar, true));
                } else if (pedidoPendiente) {
                    agregar(texto(`Esperando que ${config.tarotista} responda tu pedido…`));
                } else {
                    agregar(...formularioPedido());
                }
                desactivar(barra, ocupada);

                return;
            }

            // --- Con una lectura en curso ---
            const { estado } = lectura;
            const cantidad = lectura.cartas_esperadas; // la tirada + las aclaratorias
            $('#estado').textContent = config.etiquetas[estado] + (estado === 'eligiendo' ? ` ${elegidas}/${cantidad}` : '');

            if (soyTarotista) {
                if (estado === 'lista') agregar(boton('Mezclar', acciones.alMezclar));
                if (estado === 'eligiendo') agregar(texto(`${config.consultante} está eligiendo sus cartas…`));
                if (estado === 'revelando') {
                    agregar(texto('Tocá una carta de la tirada para revelarla, o:'), boton('Revelar la siguiente', acciones.alRevelarSiguiente));
                }
                if (estado === 'abierta') agregar(boton('Cerrar lectura', acciones.alCerrarLectura));
                agregar(boton('Finalizar consulta', acciones.alFinalizar, true));
            } else {
                if (estado === 'lista') agregar(texto(`${config.tarotista} va a mezclar el mazo.`));
                if (estado === 'eligiendo') agregar(texto(`Elegí ${cantidad - elegidas} carta${cantidad - elegidas === 1 ? '' : 's'} del abanico.`));
                if (estado === 'revelando') agregar(texto(`${config.tarotista} está revelando tus cartas…`));
                if (estado === 'abierta') {
                    const aclarables = config.tipos[lectura.tipo].aclaratorias
                        ? cartas.filter((c) => c.aclara === null && !cartas.some((o) => o.aclara === c.posicion))
                        : [];
                    if (pedidoPendiente) agregar(texto(`Esperando que ${config.tarotista} responda tu pedido…`));
                    else if (aclarables.length) agregar(...formularioAclaratoria(lectura, aclarables));
                    else agregar(texto(reveladas === 1 ? 'Ya se reveló la carta. Charlala en el chat.' : `Ya se revelaron ${reveladas} cartas. Charlalas en el chat.`));
                }
            }

            desactivar(barra, ocupada);
        },

        /** Agrega una carta revelada al panel lateral (payload de CartaRevelada). */
        agregarRevelada({ posicion, nombre_posicion: lugar, invertida, carta, respuesta = null }) {
            $('#sin-reveladas').hidden = true;
            const item = document.createElement('li');
            item.dataset.posicion = posicion;
            item.append(
                texto(lugar.toUpperCase(), 'text-xs tracking-widest text-[#C9A54C]'),
                texto(carta.nombre, 'text-base font-semibold'),
                texto(invertida ? 'Invertida' : 'Al derecho', 'text-xs text-[#C97B8E]'),
                ...(respuesta ? [texto(`Respuesta: ${respuesta.texto}`, 'text-base font-semibold text-[#C9A54C]')] : []),
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
