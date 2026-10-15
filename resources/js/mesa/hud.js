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
        for (const [valor, { nombre }] of Object.entries(config.tipos)) tipo.append(new Option(nombre, valor));
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

    /**
     * Datos de nacimiento, como en las páginas de cartas astrales: provincia → ciudad.
     * La provincia define la zona horaria (con su historia de cambios de horario);
     * la ciudad, la latitud y la longitud que necesita el Ascendente.
     */
    function formularioNacimiento() {
        const campo = (tipo, clase = 'campo-chico') => Object.assign(document.createElement(tipo), { className: clase });
        const fecha = Object.assign(campo('input'), { type: 'date', max: new Date().toISOString().slice(0, 10), min: '1900-01-01', required: true });
        const hora = Object.assign(campo('input'), { type: 'time', required: true });
        const sinHora = Object.assign(document.createElement('input'), { type: 'checkbox', className: 'accent-[#C9A54C]' });
        const etiquetaSinHora = Object.assign(document.createElement('label'), { className: 'flex items-center gap-1 text-xs' });
        etiquetaSinHora.append(sinHora, 'No sé mi hora');
        sinHora.addEventListener('change', () => (hora.disabled = sinHora.checked));

        const provincia = campo('select');
        const ciudad = campo('select');
        const provincias = [...new Set(config.ciudades.map((c) => c.provincia))];
        provincia.append(new Option('Provincia…', ''), ...provincias.map((p) => new Option(p, p)));
        provincia.addEventListener('change', () => {
            ciudad.replaceChildren(...config.ciudades.filter((c) => c.provincia === provincia.value).map((c) => new Option(c.nombre, c.id)));
        });

        return [
            texto('Tu nacimiento:'),
            fecha,
            hora,
            etiquetaSinHora,
            provincia,
            ciudad,
            boton('Calcular mi carta', () => {
                if (!fecha.value || !ciudad.value || (!sinHora.checked && !hora.value)) return hud.aviso('Completá la fecha, la hora (o «No sé mi hora») y la ciudad.');
                acciones.alEnviarNacimiento({
                    fecha: fecha.value,
                    hora: sinHora.checked ? null : hora.value,
                    hora_desconocida: sinHora.checked,
                    ciudad_id: Number(ciudad.value),
                });
            }),
            texto('¿Tu ciudad no está? Elegí la más cercana de tu provincia.', 'basis-full text-center text-xs text-[#F3EBDD]/50'),
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

            // --- Carta astral ---
            if (lectura.tipo === 'carta_astral') {
                $('#estado').textContent = lectura.calculando ? 'Calculando…' : config.etiquetas[lectura.estado];
                if (lectura.estado === 'esperando_datos') {
                    if (soyTarotista) agregar(texto(lectura.calculando ? 'Calculando la carta…' : `Esperando los datos de nacimiento de ${config.consultante}…`));
                    else if (lectura.calculando) agregar(texto('Calculando tu carta…'));
                    else agregar(...formularioNacimiento());
                } else if (soyTarotista) {
                    for (const [clave, nombre] of Object.entries(config.puntos)) {
                        if (lectura.resultado?.puntos[clave]) agregar(boton(`Señalar ${nombre}`, () => acciones.alSenalar(clave)));
                    }
                    agregar(boton('Cerrar lectura', acciones.alCerrarLectura));
                } else {
                    agregar(texto('Tu carta está lista: mirala en el panel y charlala en el chat.'));
                }
                if (soyTarotista) agregar(boton('Finalizar consulta', acciones.alFinalizar, true));
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

        /** El panel de la carta astral: el nacimiento (si se ve) y la tríada con su interpretación. */
        mostrarCartaAstral(lectura) {
            hud.limpiarReveladas();
            if (!lectura.resultado) return;
            $('#sin-reveladas').hidden = true;

            if (lectura.nacimiento) {
                const { fecha, hora, lugar } = lectura.nacimiento;
                const [a, m, d] = fecha.split('-');
                const li = document.createElement('li');
                li.append(texto(`Nació el ${d}/${m}/${a}${hora ? ` a las ${hora}` : ' (hora desconocida)'} en ${lugar}.`, 'text-xs text-[#F3EBDD]/60'));
                $('#reveladas').append(li);
            }
            const iconos = { sol: '☉', luna: '☽', ascendente: 'AC' };
            for (const [clave, punto] of Object.entries(lectura.resultado.puntos)) {
                const li = document.createElement('li');
                li.dataset.punto = clave;
                li.className = 'rounded-lg p-2 transition-colors';
                li.append(
                    texto(`${iconos[clave]} ${config.puntos[clave].replace(/^(el|la) /, '').toUpperCase()}`, 'text-xs tracking-widest text-[#C9A54C]'),
                    texto(`${punto.signo_nombre} ${punto.grado}${punto.aproximada ? ' (aproximada)' : ''}`, 'text-base font-semibold'),
                    texto(punto.interpretacion),
                );
                $('#reveladas').append(li);
            }
            if (!lectura.resultado.hora_conocida) {
                const li = document.createElement('li');
                li.append(texto('Sin la hora de nacimiento no se puede calcular el Ascendente.', 'text-xs text-[#F3EBDD]/50'));
                $('#reveladas').append(li);
            }
        },

        /** PlanetaSenalado: el punto se ilumina en el panel. */
        resaltarPunto(clave) {
            document.querySelectorAll('[data-punto]').forEach((li) => li.classList.toggle('bg-[#2A1E4A]', li.dataset.punto === clave));
            document.querySelector(`[data-punto="${clave}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        },

        limpiarReveladas() {
            $('#reveladas').replaceChildren();
            $('#sin-reveladas').hidden = false;
        },
    };

    return hud;
}
