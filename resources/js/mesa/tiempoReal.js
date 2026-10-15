// Todo lo que pasa por Reverb: canal de presencia, eventos, whispers y estado de la conexión.
// No conoce Three.js: solo traduce lo que llega a llamadas a los "manejadores" de mesa.js.

import '../echo.js';

// Mismos nombres que broadcastAs() en PHP. El punto inicial le dice a Echo
// que no les agregue el namespace App\Events (como en la guía).
export const EVENTOS = [
    // la consulta y el chat
    'MensajeEnviado', 'PedidoResuelto', 'LecturaIniciada', 'EstadoCambiado', 'ConsultaFinalizada',
    // la mesa
    'MazoMezclado', 'CartaElegida', 'CartaRevelada',
];

export function conectar(uuid, manejadores) {
    // join() = canal de PRESENCIA de la consulta: Laravel autoriza en routes/channels.php
    // y devuelve quiénes están (id, name, rol).
    const canal = window.Echo.join(`consulta.${uuid}`)
        // here() se dispara al suscribirse... y otra vez después de cada reconexión.
        .here((miembros) => manejadores.alSuscribirse(miembros))
        .joining((miembro) => manejadores.alEntrar(miembro))
        .leaving((miembro) => manejadores.alSalir(miembro))
        .error((error) => manejadores.alError(error))
        // Whisper: cliente → Reverb → la otra persona. No pasa por Laravel ni por la base.
        .listenForWhisper('hover', (datos) => manejadores.alHoverRemoto(datos))
        .listenForWhisper('escribiendo', () => manejadores.alEscribiendoRemoto());

    for (const nombre of EVENTOS) {
        canal.listen(`.${nombre}`, (payload) => manejadores.alEvento(nombre, payload));
    }

    window.Echo.connector.pusher.connection.bind('state_change', ({ current }) => manejadores.alCambiarConexion(current));

    return {
        susurrarHover(indice) {
            canal.whisper('hover', { indice });
        },
        susurrarEscribiendo() {
            canal.whisper('escribiendo', {});
        },
    };
}
