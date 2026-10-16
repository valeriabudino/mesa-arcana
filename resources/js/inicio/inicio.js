// El inicio: la carta del día (oráculo, plan v3 §13.5). Boca abajo hasta las 9 (hora
// argentina); cuando el servidor la revela llega OraculoRevelado por el canal PÚBLICO
// "oraculo" y gira en todas las pantallas que tengan el inicio abierto.

import '../echo.js';
import { sonido } from '../sonido.js';
import { crearFondo } from './fondo3d.js';

const { revelado, proxima } = JSON.parse(document.getElementById('datos-oraculo').textContent);
const panel = document.getElementById('oraculo');
const $ = (selector) => panel.querySelector(selector);

const fondo = crearFondo(document.getElementById('fondo'), {
    modo: 'carta',
    frente: revelado?.carta.imagen ?? null,
    invertida: revelado?.invertida ?? false,
});

function mostrar(oraculo) {
    $('[data-nombre]').textContent = oraculo.carta.nombre;
    $('[data-orientacion]').textContent = oraculo.invertida ? 'Invertida' : 'Al derecho';
    $('[data-mensaje]').textContent = oraculo.mensaje;
    $('[data-oculto]').hidden = true;
    $('[data-revelado]').hidden = false;
}

// Cuenta regresiva hasta la próxima revelación.
let reloj = null;
if (!revelado) {
    const hasta = new Date(proxima).getTime();
    const dos = (n) => String(n).padStart(2, '0');
    const tic = () => {
        const falta = Math.max(0, Math.floor((hasta - Date.now()) / 1000));
        $('[data-cuenta]').textContent = falta > 0
            ? `${dos(Math.floor(falta / 3600))}:${dos(Math.floor((falta % 3600) / 60))}:${dos(falta % 60)}`
            : 'Se revela en instantes…';
    };
    tic();
    reloj = setInterval(tic, 1000);
}

window.Echo.channel('oraculo').listen('.OraculoRevelado', (oraculo) => {
    clearInterval(reloj);
    mostrar(oraculo);
    fondo?.revelar(oraculo.carta.imagen, oraculo.invertida);
    sonido.revelar();
});
