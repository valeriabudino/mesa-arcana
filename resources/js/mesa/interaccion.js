// Raycaster: traduce el mouse (o el dedo) sobre el canvas a "qué carta del abanico".
// No decide nada: solo avisa por callbacks. Quién puede elegir lo decide mesa.js
// (y, de verdad, el servidor).

import * as THREE from 'three';

export function crearInteraccion({ canvas, camara, objetivos, alPasar, alTocar }) {
    const raycaster = new THREE.Raycaster();
    const puntero = new THREE.Vector2();
    let actual = null;
    let habilitada = true;
    let inicioToque = null;

    const cartaBajo = (evento) => {
        const r = canvas.getBoundingClientRect();
        puntero.set(((evento.clientX - r.left) / r.width) * 2 - 1, -((evento.clientY - r.top) / r.height) * 2 + 1);
        raycaster.setFromCamera(puntero, camara);

        return raycaster.intersectObjects(objetivos(), false)[0]?.object.userData.carta ?? null;
    };

    const cambiar = (carta) => {
        if (carta === actual) return;
        actual = carta;
        canvas.style.cursor = carta ? 'pointer' : '';
        alPasar(carta);
    };

    canvas.addEventListener('pointermove', (e) => {
        if (habilitada && e.pointerType === 'mouse') cambiar(cartaBajo(e));
    });
    canvas.addEventListener('pointerleave', () => cambiar(null));

    // Un "toque" es apretar y soltar sin arrastrar (así el scroll en el celu no elige cartas).
    canvas.addEventListener('pointerdown', (e) => (inicioToque = { x: e.clientX, y: e.clientY }));
    canvas.addEventListener('pointerup', (e) => {
        if (!habilitada || !inicioToque) return;
        const movido = Math.hypot(e.clientX - inicioToque.x, e.clientY - inicioToque.y);
        inicioToque = null;
        if (movido > 8) return;

        const carta = cartaBajo(e);
        if (carta) alTocar(carta);
    });

    return {
        habilitar(valor) {
            habilitada = valor;
            if (!valor) cambiar(null);
        },
    };
}
