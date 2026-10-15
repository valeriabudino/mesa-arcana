// El chat de la sala. No conoce Echo ni Three.js: mesa.js le pasa los mensajes.
// También es donde se sigue la lectura: cada carta revelada y la carta astral
// calculada llegan como mensajes (tipo "carta" y "carta_astral").
//
// Regla de seguridad: TODO lo que escribió una persona se pone con textContent,
// nunca con innerHTML. Así "<img onerror=…>" se ve como texto y no se ejecuta.

const $ = (selector) => document.querySelector(selector);

// En el celu el chat va abajo y puede estar plegado (solo se ve la barra).
const celu = window.matchMedia('(max-width: 767.98px)');
const plegado = () => celu.matches && $('#panel').dataset.plegado === 'true';

const ICONOS_ASTRALES = { sol: '☉', luna: '☽', ascendente: 'AC' };

export function crearChat(config, { alEnviar, alResponderPedido, alEscribir, nombrePosicion, nacimientoDe }) {
    const soyTarotista = config.yo.rol === 'tarotista';
    const mensajes = new Map(); // id → mensaje (por id: un mensaje repetido no se dibuja dos veces)
    let ultimoId = 0;
    let noLeidos = 0;
    let temporizadorEscribiendo;

    const nodo = (tag, clase, texto) => {
        const el = document.createElement(tag);
        if (clase) el.className = clase;
        if (texto !== undefined) el.textContent = texto;

        return el;
    };

    const tarjeta = (disposicion) => nodo('div', `w-full rounded-lg border border-[#C9A54C]/60 bg-[#2A1E4A]/60 p-3 ${disposicion}`);
    const titulo = (texto) => nodo('p', 'text-[10px] uppercase tracking-widest text-[#C9A54C]', texto);

    /** Una carta revelada: la imagen (girada si salió invertida), la posición y el significado. */
    function dibujarCarta(li, { nombre_posicion, invertida, carta, respuesta = null }) {
        const caja = tarjeta('flex gap-3');
        const imagen = nodo('img', `h-24 w-14 shrink-0 rounded object-cover ${invertida ? 'rotate-180' : ''}`);
        imagen.src = carta.imagen;
        imagen.alt = carta.nombre;
        imagen.loading = 'lazy';
        const texto = nodo('div', 'min-w-0 space-y-1');
        texto.append(
            titulo(`✦ ${nombre_posicion}`),
            nodo('p', 'text-base font-semibold', carta.nombre),
            nodo('p', 'text-xs text-[#C97B8E]', invertida ? 'Invertida' : 'Al derecho'),
            ...(respuesta ? [nodo('p', 'text-base font-semibold text-[#C9A54C]', `Respuesta: ${respuesta.texto}`)] : []),
            nodo('p', '', carta.significado),
            nodo('p', 'text-xs text-[#F3EBDD]/50', carta.palabras_clave.join(' · ')),
        );
        caja.append(imagen, texto);
        li.append(caja);
    }

    function textoNacimiento({ fecha, hora, lugar }) {
        const [a, m, d] = fecha.split('-');

        return `Nació el ${d}/${m}/${a}${hora ? ` a las ${hora}` : ' (hora desconocida)'} en ${lugar}.`;
    }

    /** La carta astral: la tríada con su interpretación. El nacimiento, solo si se conoce acá. */
    function dibujarAstral(li, { lectura, resultado }) {
        li.dataset.astral = lectura;
        const caja = tarjeta('space-y-2');
        const nacimiento = nodo('p', 'text-xs text-[#F3EBDD]/60');
        nacimiento.dataset.nacimiento = '';
        const datos = nacimientoDe(lectura);
        nacimiento.hidden = !datos;
        if (datos) nacimiento.textContent = textoNacimiento(datos);
        caja.append(titulo('✦ Carta astral'), nacimiento);

        for (const [clave, punto] of Object.entries(resultado.puntos)) {
            const fila = nodo('div', 'rounded-md p-1.5 transition-colors');
            fila.dataset.punto = clave;
            fila.append(
                titulo(`${ICONOS_ASTRALES[clave]} ${config.puntos[clave].replace(/^(el|la) /, '')}`),
                nodo('p', 'text-base font-semibold', `${punto.signo_nombre} ${punto.grado}${punto.aproximada ? ' (aproximada)' : ''}`),
                nodo('p', '', punto.interpretacion),
            );
            caja.append(fila);
        }
        if (!resultado.hora_conocida) {
            caja.append(nodo('p', 'text-xs text-[#F3EBDD]/50', 'Sin la hora de nacimiento no se puede calcular el Ascendente.'));
        }
        li.append(caja);
    }

    function dibujar(m) {
        const li = nodo('li');
        li.dataset.id = m.id;

        if (m.tipo === 'sistema') {
            li.className = 'text-center text-xs text-[#C9A54C]/80';
            li.textContent = m.contenido;

            return li;
        }
        if (m.tipo === 'carta' || m.tipo === 'carta_astral') {
            li.className = 'flex justify-center';
            m.tipo === 'carta' ? dibujarCarta(li, m.revelada) : dibujarAstral(li, m);

            return li;
        }

        const propio = m.autor?.rol === config.yo.rol;
        li.className = `flex ${propio ? 'justify-end' : 'justify-start'}`;
        const burbuja = nodo('div', `max-w-[85%] rounded-lg px-3 py-2 ${
            m.autor?.rol === 'tarotista' ? 'bg-[#2A1E4A]' : 'bg-[#F3EBDD] text-[#0E0B1A]'
        }`);
        burbuja.append(nodo('p', 'text-[10px] uppercase tracking-wider opacity-60', m.autor?.nombre ?? ''));

        if (m.tipo === 'pedido_servicio' || m.tipo === 'pedido_aclaratoria') {
            burbuja.classList.add('border', 'border-[#C9A54C]');
            const que = m.tipo === 'pedido_servicio'
                ? config.tipos[m.servicio]?.nombre ?? m.servicio
                : `aclarar «${nombrePosicion(Number(m.ancla?.valor)) ?? 'una carta'}»`;
            burbuja.append(nodo('p', 'font-semibold', `✦ Pidió: ${que}`));
            if (m.pregunta) {
                burbuja.append(
                    nodo('p', 'text-xs opacity-70', `${m.pregunta.tema.icono} ${m.pregunta.tema.nombre}`),
                    nodo('p', 'italic', `«${m.pregunta.texto}»`),
                );
            }

            if (m.estado_pedido === 'pendiente' && soyTarotista) {
                const botones = nodo('div', 'mt-2 flex gap-2');
                const aceptar = nodo('button', 'boton !px-2 !py-1 text-xs', 'Aceptar');
                const rechazar = nodo('button', 'boton !bg-transparent !px-2 !py-1 text-xs', 'Rechazar');
                aceptar.addEventListener('click', () => alResponderPedido(m.id, true));
                rechazar.addEventListener('click', () => alResponderPedido(m.id, false));
                botones.append(aceptar, rechazar);
                burbuja.append(botones);
            } else {
                const estados = { pendiente: 'Esperando respuesta…', aceptado: 'Aceptado', rechazado: 'No aceptado', cancelado: 'Cancelado' };
                burbuja.append(nodo('p', 'mt-1 text-xs opacity-70', estados[m.estado_pedido] ?? ''));
            }
        } else {
            burbuja.append(nodo('p', 'whitespace-pre-wrap break-words', m.contenido));
        }

        li.append(burbuja);

        return li;
    }

    function reemplazarODibujar(m) {
        const lista = $('#chat-lista');
        const viejo = lista.querySelector(`[data-id="${m.id}"]`);
        const nuevo = dibujar(m);
        if (viejo) return viejo.replaceWith(nuevo);

        // En orden de id, aunque lleguen desordenados (foto + eventos).
        const siguiente = [...lista.children].find((li) => Number(li.dataset.id) > m.id);
        lista.insertBefore(nuevo, siguiente ?? null);
        lista.scrollTop = lista.scrollHeight;
    }

    /** La carta astral más reciente de esta lectura (o de cualquiera, si no se dice). */
    const astral = (lectura) => [...document.querySelectorAll(lectura ? `[data-astral="${lectura}"]` : '[data-astral]')].at(-1);

    const chat = {
        get ultimoId() {
            return ultimoId;
        },

        /** Un mensaje (por evento o por la foto). Si ya estaba, no se duplica. */
        agregar(m) {
            if (mensajes.has(m.id)) return;
            mensajes.set(m.id, m);
            ultimoId = Math.max(ultimoId, m.id);
            reemplazarODibujar(m);
            // Con el chat plegado, se cuentan los mensajes de la otra persona y las cartas.
            const deLaOtra = m.autor && m.autor.rol !== config.yo.rol;
            if ((deLaOtra || m.tipo === 'carta' || m.tipo === 'carta_astral') && plegado()) {
                noLeidos++;
                $('#no-leidos').textContent = noLeidos;
                $('#no-leidos').hidden = false;
            }
            if (deLaOtra) $('#escribiendo').textContent = '';
        },

        hayPedidoPendiente() {
            return chat.pedidoPendiente() !== null;
        },

        /** El id del pedido que espera respuesta (hay como mucho uno), o null. */
        pedidoPendiente() {
            return [...mensajes.values()].find((m) => m.estado_pedido === 'pendiente')?.id ?? null;
        },

        /** PedidoResuelto: la tarjeta cambia de "pendiente" a aceptado/rechazado/cancelado. */
        resolverPedido({ mensaje, estado_pedido }) {
            const m = mensajes.get(mensaje);
            if (!m) return;
            m.estado_pedido = estado_pedido;
            reemplazarODibujar(m);
        },

        /** Los datos de nacimiento llegan aparte (de /estado): se suman a la carta astral. */
        mostrarNacimiento(lectura, datos) {
            const linea = astral(lectura)?.querySelector('[data-nacimiento]');
            if (!linea || !datos) return;
            linea.textContent = textoNacimiento(datos);
            linea.hidden = false;
        },

        /** PlanetaSenalado: el punto se ilumina en la carta astral del chat. */
        resaltarPunto(clave) {
            const caja = astral();
            if (!caja) return;
            caja.querySelectorAll('[data-punto]').forEach((fila) => fila.classList.toggle('bg-[#3A2A63]', fila.dataset.punto === clave));
            caja.querySelector(`[data-punto="${clave}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        },

        /** Whisper "escribiendo" de la otra persona: se muestra 3 s. */
        escribiendo(nombre) {
            $('#escribiendo').textContent = `${nombre} está escribiendo…`;
            clearTimeout(temporizadorEscribiendo);
            temporizadorEscribiendo = setTimeout(() => ($('#escribiendo').textContent = ''), 3000);
        },

        habilitar(valor) {
            $('#chat-texto').disabled = !valor;
            $('#chat-form button').disabled = !valor;
            $('#chat-texto').placeholder = valor ? 'Escribí un mensaje…' : 'La consulta terminó.';
        },

        /** Abre o pliega el chat (solo cambia algo en el celu). */
        plegar(valor) {
            $('#panel').dataset.plegado = String(valor);
            $('#plegar').setAttribute('aria-expanded', String(!valor));
            if (!plegado()) {
                // Lo que se está viendo deja de estar "sin leer".
                noLeidos = 0;
                $('#no-leidos').hidden = true;
                $('#chat-lista').scrollTop = $('#chat-lista').scrollHeight;
            }
        },
    };

    $('#plegar').addEventListener('click', () => {
        if (celu.matches) chat.plegar(!plegado());
    });

    $('#chat-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const texto = $('#chat-texto').value.trim();
        if (!texto) return;
        $('#chat-texto').value = '';
        // El mensaje aparece cuando vuelve por Reverb (igual que las cartas): una sola fuente de verdad.
        const ok = await alEnviar(texto);
        if (!ok) $('#chat-texto').value = texto; // si falló, no se pierde lo escrito
    });

    // "Escribiendo…": como mucho un whisper cada 2 s.
    let ultimoAviso = 0;
    $('#chat-texto').addEventListener('input', () => {
        if (performance.now() - ultimoAviso > 2000) {
            ultimoAviso = performance.now();
            alEscribir();
        }
    });

    return chat;
}
