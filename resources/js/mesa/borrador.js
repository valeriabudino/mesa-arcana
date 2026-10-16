// El borrador de la interpretación (plan v3 §13.6), solo en la pantalla de la tarotista.
// Llega por su canal privado mientras la IA escribe (BorradorInterpretacion, con el texto
// acumulado); al terminar queda editable y se publica en el chat de la consulta.

const $ = (selector) => document.querySelector(selector);

export function crearBorrador({ alPublicar, alMostrar }) {
    const caja = $('#borrador');
    if (!caja) return { mostrar() {}, ocultar() {}, de: () => null }; // la consultante no lo tiene

    const texto = $('#borrador-texto');
    const estado = $('#borrador-estado');
    const publicar = $('#borrador-publicar');
    let lectura = null;
    let editable = false;

    publicar.addEventListener('click', async () => {
        const contenido = texto.value.trim();
        if (!contenido || lectura === null) return;
        publicar.disabled = true;
        const ok = await alPublicar(lectura, contenido);
        publicar.disabled = false;
        if (ok) borrador.ocultar();
    });

    const borrador = {
        /** BorradorInterpretacion (o el que vino en /estado al recargar). */
        mostrar({ lectura: id, texto: contenido, terminado, fuente }) {
            if (lectura === id && editable) return; // ya lo está editando: no se le pisa
            lectura = id;
            texto.value = contenido;
            texto.scrollTop = texto.scrollHeight;
            editable = terminado;
            texto.readOnly = !terminado;
            publicar.disabled = !terminado;
            estado.textContent = !terminado
                ? 'La IA está escribiendo…'
                : fuente === 'base' ? 'Armado con los significados de las cartas (sin IA). Revisalo y publicalo.' : 'Lo escribió la IA: revisalo, editalo si querés y publicalo.';
            if (caja.hidden) {
                caja.hidden = false;
                alMostrar();
            }
        },

        ocultar() {
            caja.hidden = true;
            lectura = null;
            editable = false;
            texto.value = '';
        },

        /** De qué lectura es el borrador que se está viendo (o null). */
        de: () => lectura,
    };

    return borrador;
}
