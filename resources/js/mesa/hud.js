// El HTML encima del canvas: presencia, estado y barra de acción. (La lectura se sigue en el chat.)
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

// data-bloqueado: un campo que tiene que seguir apagado aunque la barra se habilite (la hora con «No sé mi hora»).
const desactivar = (barra, ocupada) =>
    barra.querySelectorAll('button, select, input').forEach((el) => (el.disabled = ocupada || el.dataset.bloqueado === 'true'));

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

    /**
     * Con la lectura terminada, la consultante elige cómo seguir: aclarar una de las cartas
     * (si la tirada lo admite) o pedir otra lectura. Si la tarotista acepta otra lectura,
     * la actual se cierra sola.
     */
    function formularioSiguiente(lectura, aclarables) {
        const que = document.createElement('select');
        que.className = 'campo-chico max-w-full';
        if (aclarables.length) {
            const aclarar = Object.assign(document.createElement('optgroup'), { label: 'Aclarar una carta' });
            for (const c of aclarables) aclarar.append(new Option(`Aclarar «${config.tipos[lectura.tipo].posiciones[c.posicion]}»`, `aclarar:${c.posicion}`));
            que.append(aclarar);
        }
        const otra = Object.assign(document.createElement('optgroup'), { label: 'Otra lectura' });
        for (const [valor, { nombre }] of Object.entries(config.tipos)) otra.append(new Option(nombre, `nueva:${valor}`));
        que.append(otra);
        const pregunta = document.createElement('input');
        pregunta.className = 'campo-chico w-56 max-w-full';
        pregunta.maxLength = 500;
        pregunta.placeholder = 'Tu pregunta (opcional)';

        return [
            texto('¿Seguimos?'),
            que,
            pregunta,
            boton('Pedir', () => {
                const [accion, valor] = que.value.split(':');
                if (accion === 'aclarar') acciones.alPedirAclaratoria(Number(valor), pregunta.value.trim());
                else acciones.alPedir(valor, pregunta.value.trim());
            }),
        ];
    }

    /** Mientras espera la respuesta, la consultante puede retirar su pedido (y elegir otra cosa). */
    const esperandoRespuesta = () => [
        texto(`Esperando que ${config.tarotista} responda tu pedido…`),
        boton('Cancelar pedido', acciones.alCancelarPedido, true),
    ];

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
        sinHora.addEventListener('change', () => (hora.disabled = hora.dataset.bloqueado = sinHora.checked));

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

    let barraDibujada = null; // el HTML de la última barra puesta (ver mostrar)

    /** El contenido de la barra de acción según rol × estado (se dibuja en `barra`, que todavía no está en la página). */
    function armarBarra(barra, { estadoConsulta, urlResumen, lectura, cartas, elegidas, pedidoPendiente }) {
        const agregar = (...elementos) => barra.append(...elementos);

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
                agregar(...esperandoRespuesta());
            } else {
                agregar(...formularioPedido());
            }

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
                if (pedidoPendiente) agregar(texto('Tenés un pedido para responder en el chat.'));
                for (const [clave, nombre] of Object.entries(config.puntos)) {
                    if (lectura.resultado?.puntos[clave]) agregar(boton(`Señalar ${nombre}`, () => acciones.alSenalar(clave)));
                }
                agregar(boton('Cerrar lectura', acciones.alCerrarLectura));
            } else if (pedidoPendiente) {
                agregar(...esperandoRespuesta());
            } else {
                agregar(texto('Tu carta está en el chat.'), ...formularioSiguiente(lectura, []));
            }
            if (soyTarotista) agregar(boton('Finalizar consulta', acciones.alFinalizar, true));

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
            if (estado === 'abierta') {
                if (pedidoPendiente) agregar(texto('Tenés un pedido para responder en el chat.'));
                agregar(boton('Cerrar lectura', acciones.alCerrarLectura));
            }
            agregar(boton('Finalizar consulta', acciones.alFinalizar, true));
        } else {
            if (estado === 'lista') agregar(texto(`${config.tarotista} va a mezclar el mazo.`));
            if (estado === 'eligiendo') agregar(texto(`Elegí ${cantidad - elegidas} carta${cantidad - elegidas === 1 ? '' : 's'} del abanico.`));
            if (estado === 'revelando') agregar(texto(`${config.tarotista} está revelando tus cartas…`));
            if (estado === 'abierta') {
                const aclarables = config.tipos[lectura.tipo].aclaratorias
                    ? cartas.filter((c) => c.aclara === null && !cartas.some((o) => o.aclara === c.posicion))
                    : [];
                if (pedidoPendiente) agregar(...esperandoRespuesta());
                else agregar(...formularioSiguiente(lectura, aclarables));
            }
        }
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
        mostrar(datos) {
            // La barra se arma aparte y solo se cambia si cambió lo que muestra. Si se rearmara
            // siempre, cada 15 s o con cada mensaje del chat se borraría lo que la persona estaba
            // escribiendo en un formulario (los datos de nacimiento, la pregunta…).
            const nueva = document.createElement('div');
            armarBarra(nueva, datos);
            const barra = $('#acciones');
            if (nueva.innerHTML !== barraDibujada) {
                barraDibujada = nueva.innerHTML;
                barra.replaceChildren(...nueva.childNodes);
            }
            desactivar(barra, datos.ocupada);
        },
    };

    return hud;
}
