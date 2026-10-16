// Fondo 3D liviano para el inicio y la sala de espera: la carta del día (modo "carta")
// o el mazo flotando (modo "mazo"), con polvo dorado. Reutiliza las texturas de la mesa.
// La carta del día se balancea boca abajo hasta que se revela; ahí gira y queda de frente.
// Sin WebGL, no hace nada: la página se ve igual, solo sin el fondo.

import * as THREE from 'three';
import { hayWebGL, reducirMovimiento } from '../mesa/escena.js';
import { CARTA } from '../mesa/layouts.js';
import { crearDorso, crearHalo } from '../mesa/texturas.js';

export function crearFondo(contenedor, { modo = 'carta', frente = null, invertida = false } = {}) {
    if (!hayWebGL()) return null;

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.domElement.classList.add('block', 'size-full');
    contenedor.appendChild(renderer.domElement);

    const escena = new THREE.Scene();
    const camara = new THREE.PerspectiveCamera(35, 1, 0.1, 50);
    camara.position.set(0, 0, 6);

    escena.add(new THREE.AmbientLight('#8C7BC4', 1.4));
    const vela = new THREE.PointLight('#FFB36B', 30, 0, 1.6);
    vela.position.set(-2, 1.5, 3);
    escena.add(vela);

    // La carta: la misma caja finita de la mesa (frente +z, dorso −z).
    const geometria = new THREE.BoxGeometry(CARTA.ancho * 2, CARTA.alto * 2, 0.02);
    const dorso = new THREE.MeshStandardMaterial({ map: crearDorso(renderer), roughness: 0.45, metalness: 0.15 });
    const canto = new THREE.MeshStandardMaterial({ color: '#D8C79A', roughness: 0.6 });
    const frenteMaterial = new THREE.MeshStandardMaterial({ color: '#F3EBDD', roughness: 0.55 });
    const ponerFrente = (url) =>
        new THREE.TextureLoader().load(url, (textura) => {
            textura.colorSpace = THREE.SRGBColorSpace;
            frenteMaterial.map = textura;
            frenteMaterial.color.set('#ffffff');
            frenteMaterial.needsUpdate = true;
        });
    if (frente) ponerFrente(frente);

    // giro: el ángulo de la carta (π = boca abajo, 0 = de frente). Va hacia "objetivo" de a poco.
    let objetivo = frente ? 0 : Math.PI;
    let giro = objetivo;

    const grupo = new THREE.Group();
    const cartas = [];
    const cantidad = modo === 'mazo' ? 12 : 1;
    for (let i = 0; i < cantidad; i++) {
        const carta = new THREE.Mesh(geometria, [canto, canto, canto, canto, modo === 'mazo' ? dorso : frenteMaterial, dorso]);
        if (modo === 'mazo') {
            carta.rotation.y = Math.PI; // el mazo se ve del lado del dorso
            carta.position.z = -i * 0.03;
        }
        if (modo === 'carta' && invertida) carta.rotation.z = Math.PI; // salió invertida: cabeza abajo
        grupo.add(carta);
        cartas.push(carta);
    }
    escena.add(grupo);

    // Polvo dorado.
    const N = 160;
    const base = new Float32Array(N * 3);
    for (let i = 0; i < N; i++) base.set([(Math.random() - 0.5) * 9, (Math.random() - 0.5) * 6, (Math.random() - 0.5) * 3], i * 3);
    const geometriaPolvo = new THREE.BufferGeometry();
    geometriaPolvo.setAttribute('position', new THREE.BufferAttribute(base.slice(), 3));
    escena.add(new THREE.Points(geometriaPolvo, new THREE.PointsMaterial({
        map: crearHalo(renderer, '230, 201, 120'), size: 0.08, transparent: true, opacity: 0.7, blending: THREE.AdditiveBlending, depthWrite: false,
    })));

    const ajustar = () => {
        const { clientWidth: ancho, clientHeight: alto } = contenedor;
        if (!ancho || !alto) return;
        renderer.setSize(ancho, alto, false);
        camara.aspect = ancho / alto;
        camara.updateProjectionMatrix();
        // En pantallas anchas la carta va a la derecha del texto; en el celu, arriba.
        const ancha = camara.aspect > 1.1;
        grupo.position.set(ancha ? 1.5 : 0, ancha ? 0 : 1.2, 0);
        grupo.scale.setScalar(ancha ? 1 : 0.5);
    };
    new ResizeObserver(ajustar).observe(contenedor);
    ajustar();

    const quieta = reducirMovimiento();
    const posiciones = geometriaPolvo.attributes.position;
    renderer.setAnimationLoop((ms) => {
        const t = ms / 1000;
        if (!quieta) {
            if (modo === 'carta') {
                giro += (objetivo - giro) * 0.035; // al revelarse, da la vuelta despacio
                grupo.rotation.y = giro + Math.sin(t * 0.6) * 0.35; // y se balancea, sin mostrar la otra cara
                grupo.position.y += Math.sin(t * 1.1) * 0.0015;
            } else {
                grupo.rotation.set(0.25 + Math.sin(t * 0.5) * 0.06, Math.sin(t * 0.3) * 0.25, Math.sin(t * 0.4) * 0.04);
                cartas.forEach((c, i) => (c.position.y = Math.sin(t * 0.8 + i * 0.5) * 0.04 * (i % 3)));
            }
            vela.intensity = 30 * (1 + 0.1 * Math.sin(t * 7.3) + 0.05 * Math.sin(t * 13.1));
            for (let i = 0; i < N; i++) {
                posiciones.setXYZ(i, base[i * 3] + 0.2 * Math.sin(t * 0.2 + i), base[i * 3 + 1] + 0.15 * Math.sin(t * 0.3 + i * 1.7), base[i * 3 + 2]);
            }
            posiciones.needsUpdate = true;
        } else if (modo === 'carta') {
            grupo.rotation.y = objetivo + 0.35; // quieta, de tres cuartos
        }
        renderer.render(escena, camara);
    });

    return {
        renderer,
        /** OraculoRevelado: se carga el frente y la carta da la vuelta. */
        revelar(imagen, invertidaAhora) {
            if (modo !== 'carta') return;
            ponerFrente(imagen);
            cartas[0].rotation.z = invertidaAhora ? Math.PI : 0;
            // Boca abajo: media vuelta hasta el frente. Si ya estaba de frente (otra carta
            // para la demo), una vuelta entera.
            objetivo = objetivo === Math.PI ? 0 : objetivo - 2 * Math.PI;
        },
    };
}
