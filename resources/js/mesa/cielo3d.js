// Modo "cielo": la rueda zodiacal flotando sobre la mesa, con Sol, Luna y Ascendente.
// No conoce Echo: recibe el "resultado" que calculó el servidor (el mismo en las dos pantallas).
//
// Convención de las cartas natales: el Ascendente a la izquierda (9 en punto) y el
// zodíaco avanzando en sentido antihorario. Sin hora (sin Ascendente), 0° Aries a la izquierda.

import gsap from 'gsap';
import * as THREE from 'three';
import { crearHalo } from './texturas.js';

const CENTRO = new THREE.Vector3(0, 2.7, -1.3); // bien arriba de la mesa: la rueda flota, no la atraviesa
const DESDE_LA_CAMARA = new THREE.Vector3(0, 2.0, 8.4); // dónde se ubica la cámara respecto de la rueda
const SIGNOS = ['♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓'];
const PUNTOS = {
    sol: { color: '#F6C343', radio: 1.22, etiqueta: 'Sol' },
    luna: { color: '#DDE3F0', radio: 1.02, etiqueta: 'Luna' },
    ascendente: { color: '#C97B8E', radio: 2.38, etiqueta: 'Asc' },
};
const DORADO = new THREE.Color('#C9A54C');
const SUAVE = 'power2.inOut';

function textoSprite(renderer, texto, { tamano = 96, color = '#E6C978', ancho = 256 } = {}) {
    const canvas = document.createElement('canvas');
    canvas.width = ancho;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');
    // U+FE0E: que los símbolos se dibujen como texto y no como emoji de colores.
    ctx.font = `${tamano}px "DejaVu Sans", "Segoe UI Symbol", serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = color;
    ctx.fillText(`${texto}︎`, ancho / 2, 66);
    const textura = new THREE.CanvasTexture(canvas);
    textura.colorSpace = THREE.SRGBColorSpace;
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: textura, transparent: true, depthWrite: false }));
    sprite.scale.set(ancho / 256 * 0.42, 0.21, 1);

    return sprite;
}

export function crearCielo({ escena: contexto }) {
    const { escena, renderer, vista } = contexto;
    let rueda = null;
    let puntos = {};

    const enRueda = (radio, angulo) => new THREE.Vector3(radio * Math.cos(angulo), radio * Math.sin(angulo), 0);

    function quitar() {
        if (!rueda) return;
        gsap.killTweensOf(rueda.scale);
        escena.remove(rueda);
        rueda.traverse((o) => {
            o.geometry?.dispose();
            o.material?.map?.dispose();
            o.material?.dispose();
        });
        rueda = null;
        puntos = {};
    }

    /** Arma la rueda para este resultado (todavía invisible: scale 0). */
    function armar(resultado) {
        quitar();
        const asc = resultado.puntos.ascendente?.longitud ?? 0;
        const angulo = (longitud) => Math.PI + THREE.MathUtils.degToRad(longitud - asc); // antihorario, Asc a la izquierda

        rueda = new THREE.Group();
        rueda.position.copy(CENTRO);
        rueda.lookAt(CENTRO.clone().add(DESDE_LA_CAMARA)); // de frente a donde va a estar la cámara
        const giro = new THREE.Group(); // lo que rota al aparecer
        rueda.add(giro);

        const oro = new THREE.MeshBasicMaterial({ color: DORADO, side: THREE.DoubleSide, transparent: true, opacity: 0.9 });
        const anillo = (interior, exterior) => giro.add(new THREE.Mesh(new THREE.RingGeometry(interior, exterior, 128), oro));
        anillo(2.2, 2.24);
        anillo(1.62, 1.645);
        anillo(0.62, 0.635);

        // Fondo violeta translúcido.
        giro.add(new THREE.Mesh(
            new THREE.CircleGeometry(2.2, 96),
            new THREE.MeshBasicMaterial({ color: '#2A1E4A', transparent: true, opacity: 0.55, side: THREE.DoubleSide, depthWrite: false }),
        ));

        // Doce divisiones y sus símbolos.
        for (let i = 0; i < 12; i++) {
            const a = angulo(i * 30);
            const linea = new THREE.Mesh(new THREE.PlaneGeometry(0.58, 0.012), oro);
            linea.position.copy(enRueda(1.92, a));
            linea.rotation.z = a;
            giro.add(linea);

            const simbolo = textoSprite(renderer, SIGNOS[i]);
            simbolo.position.copy(enRueda(1.92, angulo(i * 30 + 15)));
            giro.add(simbolo);
        }

        // El horizonte (Ascendente–Descendente), si hay hora.
        if (resultado.puntos.ascendente) {
            const horizonte = new THREE.Mesh(new THREE.PlaneGeometry(4.4, 0.02), new THREE.MeshBasicMaterial({ color: '#C97B8E', transparent: true, opacity: 0.8 }));
            giro.add(horizonte); // horizontal: el Asc está a la izquierda por construcción
        }

        // Sol, Luna y Ascendente: esfera que brilla + halo + nombre.
        const halo = crearHalo(renderer, '255, 230, 170');
        for (const [clave, punto] of Object.entries(resultado.puntos)) {
            const estilo = PUNTOS[clave];
            const grupo = new THREE.Group();
            grupo.position.copy(enRueda(estilo.radio, angulo(punto.longitud)));

            const esfera = new THREE.Mesh(new THREE.SphereGeometry(clave === 'ascendente' ? 0.07 : 0.12, 32, 16), new THREE.MeshBasicMaterial({ color: estilo.color }));
            const brillo = new THREE.Sprite(new THREE.SpriteMaterial({ map: halo, color: estilo.color, blending: THREE.AdditiveBlending, depthWrite: false }));
            brillo.scale.setScalar(0.6);
            const nombre = textoSprite(renderer, estilo.etiqueta, { tamano: 64, color: estilo.color });
            nombre.position.set(0, -0.24, 0);
            grupo.add(esfera, brillo, nombre);
            giro.add(grupo);
            puntos[clave] = { grupo, brillo };
        }

        rueda.scale.setScalar(0.001);
        rueda.userData.giro = giro;
        escena.add(rueda);
    }

    /** Pasar a "cielo": la cámara sube y la rueda aparece girando hasta quedar en posición. */
    function entrar(resultado, { luces, lucesIniciales }) {
        armar(resultado);
        const tl = gsap.timeline();
        const camara = CENTRO.clone().add(DESDE_LA_CAMARA);

        tl.to(vista.posicion, { x: camara.x, y: camara.y, z: camara.z, duration: 2, ease: SUAVE }, 0);
        tl.to(vista.objetivo, { x: CENTRO.x, y: CENTRO.y, z: CENTRO.z, duration: 2, ease: SUAVE }, 0);
        tl.to(luces.ambiente, { intensity: lucesIniciales.ambiente * 0.35, duration: 1.6 }, 0); // la mesa se oscurece
        tl.to(luces.luna, { intensity: lucesIniciales.luna * 0.3, duration: 1.6 }, 0);
        tl.to(luces.foco, { intensity: 0, duration: 0.6 }, 0);
        tl.to(rueda.scale, { x: 1, y: 1, z: 1, duration: 1.6, ease: 'power3.out' }, 0.6);
        tl.fromTo(rueda.userData.giro.rotation, { z: -Math.PI }, { z: 0, duration: 2.2, ease: 'power3.out' }, 0.6);

        return tl.then();
    }

    /** Igual que entrar, pero de golpe (al recargar o reconectar). */
    function dibujar(resultado, { luces, lucesIniciales }) {
        armar(resultado);
        rueda.scale.setScalar(1);
        const camara = CENTRO.clone().add(DESDE_LA_CAMARA);
        vista.posicion.copy(camara);
        vista.objetivo.copy(CENTRO);
        luces.ambiente.intensity = lucesIniciales.ambiente * 0.35;
        luces.luna.intensity = lucesIniciales.luna * 0.3;
    }

    /** La tarotista señaló un punto: la cámara se acerca y el punto pulsa. */
    function senalar(clave) {
        const punto = puntos[clave];
        if (!punto) return Promise.resolve();

        const donde = punto.grupo.getWorldPosition(new THREE.Vector3());
        const camara = donde.clone().add(DESDE_LA_CAMARA.clone().setLength(3.8));
        const tl = gsap.timeline();
        tl.to(vista.posicion, { x: camara.x, y: camara.y, z: camara.z, duration: 1.4, ease: SUAVE }, 0);
        tl.to(vista.objetivo, { x: donde.x, y: donde.y, z: donde.z, duration: 1.4, ease: SUAVE }, 0);
        tl.to(punto.grupo.scale, { x: 1.7, y: 1.7, z: 1.7, duration: 0.45, yoyo: true, repeat: 3, ease: 'sine.inOut' }, 0.8);
        tl.to(punto.brillo.scale, { x: 1.4, y: 1.4, duration: 0.45, yoyo: true, repeat: 3, ease: 'sine.inOut' }, 0.8);

        return tl.then();
    }

    /** Al cerrar la lectura: la rueda se achica y desaparece. */
    function salir() {
        if (!rueda) return Promise.resolve();

        return gsap.to(rueda.scale, { x: 0.001, y: 0.001, z: 0.001, duration: 0.8, ease: 'power2.in' }).then(quitar);
    }

    return { entrar, dibujar, senalar, salir, quitar, hayRueda: () => rueda !== null };
}
