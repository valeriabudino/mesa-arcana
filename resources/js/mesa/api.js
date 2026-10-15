// Los pedidos HTTP a la sesión. Un POST NO mueve nada en pantalla: la animación
// llega después, por Reverb, en los dos navegadores a la vez.

const token = () => document.querySelector('meta[name="csrf-token"]').content;

const MENSAJES = {
    401: 'Tu sesión se cerró. Recargá la página.',
    403: 'Eso no te toca a vos.',
    419: 'La página quedó vieja. Recargala.',
    429: 'Demasiados intentos, esperá un momento.',
};

export class ErrorApi extends Error {
    constructor(status, mensaje) {
        super(mensaje);
        this.status = status;
    }
}

async function pedir(url, opciones) {
    const respuesta = await fetch(url, {
        credentials: 'same-origin',
        ...opciones,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...opciones.headers },
    });

    if (!respuesta.ok) {
        const cuerpo = await respuesta.json().catch(() => ({}));
        // 409 (fuera de orden) y 422 (validación) traen su propio mensaje del servidor.
        throw new ErrorApi(respuesta.status, MENSAJES[respuesta.status] ?? cuerpo.message ?? 'Algo salió mal. Probá de nuevo.');
    }

    return respuesta.status === 204 ? null : respuesta.json();
}

export function enviar(url, datos = {}) {
    return pedir(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token() },
        body: JSON.stringify(datos),
    });
}

export function pedirEstado(url) {
    return pedir(url, { method: 'GET' });
}
