// El chat de la sala. No conoce Echo ni Three.js: mesa.js le pasa los mensajes.
//
// Regla de seguridad: TODO lo que escribió una persona se pone con textContent,
// nunca con innerHTML. Así "<img onerror=…>" se ve como texto y no se ejecuta.

const $ = (selector) => document.querySelector(selector);

export function crearChat(config, { alEnviar, alResponderPedido, alEscribir }) {
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

    function dibujar(m) {
        const li = nodo('li');
        li.dataset.id = m.id;

        if (m.tipo === 'sistema') {
            li.className = 'text-center text-xs text-[#C9A54C]/80';
            li.textContent = m.contenido;

            return li;
        }

        const propio = m.autor?.rol === config.yo.rol;
        li.className = `flex ${propio ? 'justify-end' : 'justify-start'}`;
        const burbuja = nodo('div', `max-w-[85%] rounded-lg px-3 py-2 ${
            m.autor?.rol === 'tarotista' ? 'bg-[#2A1E4A]' : 'bg-[#F3EBDD] text-[#0E0B1A]'
        }`);
        burbuja.append(nodo('p', 'text-[10px] uppercase tracking-wider opacity-60', m.autor?.nombre ?? ''));

        if (m.tipo === 'pedido_servicio') {
            burbuja.classList.add('border', 'border-[#C9A54C]');
            burbuja.append(nodo('p', 'font-semibold', `✦ Pidió: ${config.tipos[m.servicio]?.nombre ?? m.servicio}`));
            if (m.contenido) burbuja.append(nodo('p', 'italic', `«${m.contenido}»`));

            if (m.estado_pedido === 'pendiente' && soyTarotista) {
                const botones = nodo('div', 'mt-2 flex gap-2');
                const aceptar = nodo('button', 'boton !px-2 !py-1 text-xs', 'Aceptar');
                const rechazar = nodo('button', 'boton !bg-transparent !px-2 !py-1 text-xs', 'Rechazar');
                aceptar.addEventListener('click', () => alResponderPedido(m.id, true));
                rechazar.addEventListener('click', () => alResponderPedido(m.id, false));
                botones.append(aceptar, rechazar);
                burbuja.append(botones);
            } else {
                const estados = { pendiente: 'Esperando respuesta…', aceptado: 'Aceptado', rechazado: 'No aceptado' };
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
            if (m.autor && m.autor.rol !== config.yo.rol && $('[data-panel=chat]').hidden) {
                noLeidos++;
                $('#no-leidos').textContent = noLeidos;
                $('#no-leidos').hidden = false;
            }
            if (m.autor && m.autor.rol !== config.yo.rol) $('#escribiendo').textContent = '';
        },

        hayPedidoPendiente() {
            return [...mensajes.values()].some((m) => m.estado_pedido === 'pendiente');
        },

        /** PedidoResuelto: la tarjeta cambia de "pendiente" a aceptado/rechazado. */
        resolverPedido({ mensaje, estado_pedido }) {
            const m = mensajes.get(mensaje);
            if (!m) return;
            m.estado_pedido = estado_pedido;
            reemplazarODibujar(m);
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

        mostrarPestana(nombre) {
            document.querySelectorAll('[data-panel]').forEach((p) => (p.hidden = p.dataset.panel !== nombre));
            document.querySelectorAll('[data-pestana]').forEach((b) => b.setAttribute('aria-selected', b.dataset.pestana === nombre));
            if (nombre === 'chat') {
                noLeidos = 0;
                $('#no-leidos').hidden = true;
                $('#chat-lista').scrollTop = $('#chat-lista').scrollHeight;
            }
        },
    };

    document.querySelectorAll('[data-pestana]').forEach((b) => b.addEventListener('click', () => chat.mostrarPestana(b.dataset.pestana)));

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

    chat.mostrarPestana('lectura');

    return chat;
}
