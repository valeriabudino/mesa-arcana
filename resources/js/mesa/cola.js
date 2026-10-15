// Cola de animaciones: si llegan dos eventos seguidos, el segundo espera a que
// termine el primero en vez de pisarlo. Si una tarea falla, la cola sigue andando.

export function crearCola() {
    let ultima = Promise.resolve();

    return function encolar(tarea) {
        const resultado = ultima.then(tarea);
        ultima = resultado.catch((error) => console.error('Falló una animación de la mesa', error));

        return resultado;
    };
}
