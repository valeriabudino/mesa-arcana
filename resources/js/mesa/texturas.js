// Texturas dibujadas por código en un <canvas>: no hace falta ningún archivo.
// Paleta del plan: noche #0E0B1A, violeta #2A1E4A, dorado #C9A54C, marfil #F3EBDD.

import * as THREE from 'three';

const DORADO = '#C9A54C';
const DORADO_CLARO = '#E6C978';

function lienzo(ancho, alto) {
    const canvas = document.createElement('canvas');
    canvas.width = ancho;
    canvas.height = alto;

    return [canvas, canvas.getContext('2d')];
}

function comoTextura(canvas, renderer) {
    const textura = new THREE.CanvasTexture(canvas);
    textura.colorSpace = THREE.SRGBColorSpace;
    textura.anisotropy = renderer.capabilities.getMaxAnisotropy();

    return textura;
}

function rectRedondeado(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, r);
}

function estrella(ctx, x, y, radio, puntas = 4) {
    ctx.beginPath();
    for (let i = 0; i < puntas * 2; i++) {
        const r = i % 2 === 0 ? radio : radio * 0.35;
        const a = (i * Math.PI) / puntas - Math.PI / 2;
        ctx.lineTo(x + r * Math.cos(a), y + r * Math.sin(a));
    }
    ctx.closePath();
    ctx.fill();
}

function luna(ctx, x, y, radio, haciaArriba) {
    // Medialuna: un círculo dorado al que se le "come" otro círculo. Se dibuja en un
    // canvas aparte para que el recorte no agujeree el dorso.
    const [aparte, c] = lienzo(radio * 2, radio * 2);
    c.fillStyle = DORADO;
    c.beginPath();
    c.arc(radio, radio, radio, 0, Math.PI * 2);
    c.fill();
    c.globalCompositeOperation = 'destination-out';
    c.beginPath();
    c.arc(radio, radio * 0.62, radio * 0.86, 0, Math.PI * 2);
    c.fill();

    ctx.save();
    ctx.translate(x, y);
    if (!haciaArriba) ctx.rotate(Math.PI);
    ctx.drawImage(aparte, -radio, -radio);
    ctx.restore();
}

/**
 * Fase lunar: k = 0 luna nueva, 0.5 llena, 1 nueva otra vez. Creciente con la luz a la
 * derecha, menguante a la izquierda. El recorte con un círculo corrido es una aproximación,
 * pero grabada en el paño no se nota.
 */
function faseLunar(ctx, x, y, radio, k) {
    const lado = Math.ceil(radio * 2 + 6);
    const [aparte, c] = lienzo(lado, lado);
    const m = lado / 2;
    const iluminada = (1 - Math.cos(2 * Math.PI * k)) / 2;

    c.fillStyle = DORADO_CLARO;
    c.beginPath();
    c.arc(m, m, radio, 0, Math.PI * 2);
    c.fill();
    c.globalCompositeOperation = 'destination-out';
    c.beginPath();
    c.arc(m + (k < 0.5 ? -1 : 1) * 2 * radio * iluminada, m, radio * 1.02, 0, Math.PI * 2);
    c.fill();
    c.globalCompositeOperation = 'source-over';
    c.strokeStyle = DORADO;
    c.lineWidth = 2;
    c.beginPath();
    c.arc(m, m, radio, 0, Math.PI * 2);
    c.stroke();

    ctx.drawImage(aparte, x - m, y - m);
}

/** Constelación inventada: unas estrellas unidas por líneas finas. */
function constelacion(ctx, cx, cy, tamano, azar) {
    const estrellas = Array.from({ length: 5 + Math.floor(azar() * 3) }, () => [
        cx + (azar() - 0.5) * tamano,
        cy + (azar() - 0.5) * tamano * 0.7,
    ]).sort((a, b) => a[0] - b[0]); // de izquierda a derecha: así las líneas no se cruzan tanto

    ctx.lineWidth = 2;
    ctx.beginPath();
    estrellas.forEach(([x, y], i) => (i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y)));
    ctx.stroke();
    for (const [x, y] of estrellas) estrella(ctx, x, y, 7 + azar() * 8);
}

/**
 * Dorso de las cartas. Es simétrico al girarlo 180°, como los mazos reales:
 * mirando el dorso no se puede saber si la carta va a salir invertida.
 */
export function crearDorso(renderer) {
    const W = 512;
    const H = Math.round((W * 1.2) / 0.7); // misma proporción que la carta
    const [canvas, ctx] = lienzo(W, H);
    const cx = W / 2;
    const cy = H / 2;

    // Fondo violeta con un degradé radial suave.
    const fondo = ctx.createRadialGradient(cx, cy, 40, cx, cy, H * 0.65);
    fondo.addColorStop(0, '#3A2A63');
    fondo.addColorStop(1, '#1A1233');
    ctx.fillStyle = fondo;
    ctx.fillRect(0, 0, W, H);

    // Lluvia de estrellitas: se dibuja una mitad y se repite girada 180° (simetría).
    let semilla = 7;
    const azar = () => ((semilla = (semilla * 16807) % 2147483647) / 2147483647);
    const puntos = Array.from({ length: 70 }, () => [azar() * W, azar() * cy, 1 + azar() * 2.2]);
    ctx.fillStyle = 'rgba(230, 201, 120, 0.55)';
    for (const [x, y, r] of puntos) {
        estrella(ctx, x, y, r * 2);
        estrella(ctx, W - x, H - y, r * 2);
    }

    // Doble marco dorado.
    ctx.strokeStyle = DORADO;
    ctx.lineWidth = 10;
    rectRedondeado(ctx, 22, 22, W - 44, H - 44, 26);
    ctx.stroke();
    ctx.lineWidth = 3;
    rectRedondeado(ctx, 44, 44, W - 88, H - 88, 16);
    ctx.stroke();

    // Sol central con rayos.
    ctx.save();
    ctx.translate(cx, cy);
    ctx.fillStyle = DORADO;
    for (let i = 0; i < 16; i++) {
        ctx.rotate(Math.PI / 8);
        ctx.beginPath();
        const largo = i % 2 === 0 ? 150 : 115;
        ctx.moveTo(-9, 0);
        ctx.lineTo(0, -largo);
        ctx.lineTo(9, 0);
        ctx.fill();
    }
    ctx.restore();

    ctx.fillStyle = '#2A1E4A';
    ctx.beginPath();
    ctx.arc(cx, cy, 72, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = DORADO_CLARO;
    ctx.lineWidth = 6;
    ctx.stroke();
    ctx.fillStyle = DORADO_CLARO;
    estrella(ctx, cx, cy, 52, 8);

    // Dos medialunas enfrentadas, arriba y abajo.
    luna(ctx, cx, cy - 250, 44, true);
    luna(ctx, cx, cy + 250, 44, false);

    // Estrellas de cuatro puntas en las esquinas interiores.
    ctx.fillStyle = DORADO;
    for (const [x, y] of [[90, 90], [W - 90, 90], [90, H - 90], [W - 90, H - 90]]) {
        estrella(ctx, x, y, 20);
    }

    return comoTextura(canvas, renderer);
}

/** Paño de terciopelo con un círculo dorado grabado alrededor de la zona de la tirada. */
export function crearPano(renderer, { ancho, profundidad, centroCirculo, radioCirculo }) {
    const PX = 160; // píxeles por unidad de mesa
    const [canvas, ctx] = lienzo(Math.round(ancho * PX), Math.round(profundidad * PX));
    const aPx = (x, z) => [(x + ancho / 2) * PX, (z + profundidad / 2) * PX];

    ctx.fillStyle = '#24183F';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Grano del terciopelo: ruido de puntitos claros y oscuros.
    const imagen = ctx.getImageData(0, 0, canvas.width, canvas.height);
    for (let i = 0; i < imagen.data.length; i += 4) {
        const ruido = (Math.random() - 0.5) * 14;
        imagen.data[i] += ruido;
        imagen.data[i + 1] += ruido;
        imagen.data[i + 2] += ruido;
    }
    ctx.putImageData(imagen, 0, 0);

    // Dibujos grabados (antes de la viñeta, así se van perdiendo en la oscuridad del borde).
    let semilla = 23;
    const azar = () => ((semilla = (semilla * 16807) % 2147483647) / 2147483647);

    // Destellos sueltos por todo el paño.
    ctx.fillStyle = 'rgba(230, 201, 120, 0.3)';
    for (let i = 0; i < 160; i++) estrella(ctx, azar() * canvas.width, azar() * canvas.height, 4 + azar() * 7);

    // Constelaciones a los costados de la tirada.
    ctx.strokeStyle = 'rgba(201, 165, 76, 0.35)';
    ctx.fillStyle = 'rgba(230, 201, 120, 0.6)';
    for (const [x, z] of [[-6.6, -0.6], [-7.0, 1.9], [-5.2, 3.3], [6.7, -1.6], [6.9, 1.2], [5.4, 3.1], [-7.2, -3.4], [7.3, -3.9]]) {
        constelacion(ctx, ...aPx(x, z), PX * 1.4, azar);
    }

    // Viñeta: más oscuro hacia los bordes, como si solo iluminara la vela.
    const [vx, vy] = aPx(centroCirculo.x, centroCirculo.z);
    const vineta = ctx.createRadialGradient(vx, vy, PX * 2, vx, vy, PX * ancho * 0.6);
    vineta.addColorStop(0, 'rgba(14, 11, 26, 0)');
    vineta.addColorStop(1, 'rgba(14, 11, 26, 0.92)');
    ctx.fillStyle = vineta;
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Círculo grabado: dos líneas y marcas como de reloj.
    const r = radioCirculo * PX;
    ctx.strokeStyle = 'rgba(201, 165, 76, 0.55)';
    ctx.lineWidth = 6;
    ctx.beginPath();
    ctx.arc(vx, vy, r, 0, Math.PI * 2);
    ctx.stroke();
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(vx, vy, r - 22, 0, Math.PI * 2);
    ctx.stroke();
    ctx.fillStyle = 'rgba(201, 165, 76, 0.55)';
    for (let i = 0; i < 12; i++) {
        const a = (i * Math.PI) / 6;
        estrella(ctx, vx + (r - 11) * Math.cos(a), vy + (r - 11) * Math.sin(a), i % 3 === 0 ? 16 : 8);
    }

    // Afuera, un anillo de astrolabio con rayitas...
    const anillo = r + PX * 0.22;
    ctx.strokeStyle = 'rgba(201, 165, 76, 0.4)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(vx, vy, anillo, 0, Math.PI * 2);
    ctx.stroke();
    for (let i = 0; i < 72; i++) {
        const a = (i * Math.PI) / 36;
        const largo = i % 6 === 0 ? 18 : 9;
        ctx.beginPath();
        ctx.moveTo(vx + anillo * Math.cos(a), vy + anillo * Math.sin(a));
        ctx.lineTo(vx + (anillo + largo) * Math.cos(a), vy + (anillo + largo) * Math.sin(a));
        ctx.stroke();
    }

    // ...y alrededor, el ciclo de la luna: nueva al fondo, llena adelante.
    ctx.globalAlpha = 0.7;
    const FASES = 12;
    for (let i = 0; i < FASES; i++) {
        const a = -Math.PI / 2 + (i * 2 * Math.PI) / FASES;
        const distancia = anillo + PX * 0.4;
        faseLunar(ctx, vx + distancia * Math.cos(a), vy + distancia * Math.sin(a), PX * 0.11, i / FASES);
    }
    ctx.globalAlpha = 1;

    return comoTextura(canvas, renderer);
}

/** Halo redondo y difuso (para la llama de la vela y el polvo dorado). */
export function crearHalo(renderer, color = '255, 190, 110') {
    const [canvas, ctx] = lienzo(128, 128);
    const g = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
    g.addColorStop(0, `rgba(${color}, 1)`);
    g.addColorStop(0.25, `rgba(${color}, 0.55)`);
    g.addColorStop(1, `rgba(${color}, 0)`);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 128, 128);

    return comoTextura(canvas, renderer);
}

/** "SÍ", "NO" o "QUIZÁS" grabado en dorado, para apoyar sobre el paño (fondo transparente). */
export function crearTextoGrabado(renderer, texto) {
    const [canvas, ctx] = lienzo(1024, 384);
    ctx.font = '600 230px Cinzel, "Times New Roman", serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.shadowColor = 'rgba(230, 201, 120, 0.9)';
    ctx.shadowBlur = 28;
    const brillo = ctx.createLinearGradient(0, 60, 0, 330);
    brillo.addColorStop(0, '#F6E2A4');
    brillo.addColorStop(0.5, DORADO);
    brillo.addColorStop(1, '#8E6F2A');
    ctx.fillStyle = brillo;
    ctx.fillText(texto.toUpperCase(), 512, 200);

    return comoTextura(canvas, renderer);
}
