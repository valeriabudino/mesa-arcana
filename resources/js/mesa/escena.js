// Escenario 3D: renderer, cámara, luces, mesa, vela, polvo y loop de render.
// No conoce Echo ni la lógica de la lectura: solo dibuja.

import * as THREE from 'three';
import WebGL from 'three/addons/capabilities/WebGL.js';
import { MAZO, puntosDeEncuadre } from './layouts.js';
import { crearHalo, crearPano } from './texturas.js';

const ELEVACION = (52 * Math.PI) / 180; // ángulo de la cámara sobre la mesa
const MARGEN = 0.92; // cuánto de la pantalla (en coordenadas -1..1) puede ocupar la escena
const VELA = new THREE.Vector3(-3.6, 0, -2.9);

export const reducirMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export function hayWebGL() {
    return WebGL.isWebGL2Available();
}

/**
 * Busca la cámara más cercana (con el ángulo fijo) en la que entran todos los puntos,
 * y corre el punto al que mira para que la escena quede centrada en la pantalla.
 * Es una función pura: se puede probar sin navegador.
 */
export function calcularEncuadre(camara, puntos) {
    const objetivo = new THREE.Box3().setFromPoints(puntos).getCenter(new THREE.Vector3());
    const posicion = new THREE.Vector3();

    const ubicar = (distancia) => {
        posicion.set(objetivo.x, objetivo.y + distancia * Math.sin(ELEVACION), objetivo.z + distancia * Math.cos(ELEVACION));
        camara.position.copy(posicion);
        camara.lookAt(objetivo);
        camara.updateMatrixWorld();
    };
    const proyectados = () => puntos.map((p) => p.clone().project(camara));
    const entra = () => proyectados().every((n) => Math.abs(n.x) <= MARGEN && Math.abs(n.y) <= MARGEN);

    let distancia = 30;
    for (let vuelta = 0; vuelta < 4; vuelta++) {
        // Búsqueda binaria de la distancia mínima en la que entra todo.
        let cerca = 1;
        let lejos = 80;
        for (let i = 0; i < 30; i++) {
            const medio = (cerca + lejos) / 2;
            ubicar(medio);
            entra() ? (lejos = medio) : (cerca = medio);
        }
        distancia = lejos;
        ubicar(distancia);

        // Por la perspectiva, lo cercano ocupa más: corremos el objetivo hacia
        // el lado que sobra para equilibrar arriba y abajo.
        const ys = proyectados().map((n) => n.y);
        const desbalance = (Math.max(...ys) + Math.min(...ys)) / 2;
        objetivo.z -= desbalance * distancia * 0.35;
    }
    ubicar(distancia);

    return { posicion: posicion.clone(), objetivo: objetivo.clone() };
}

export function crearEscena(contenedor) {
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2)); // que no explote en celulares
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFShadowMap;
    renderer.domElement.classList.add('block', 'size-full');
    contenedor.appendChild(renderer.domElement);

    const escena = new THREE.Scene();
    escena.background = new THREE.Color('#0E0B1A');
    escena.fog = new THREE.Fog('#0E0B1A', 14, 30);

    const camara = new THREE.PerspectiveCamera(40, 1, 0.1, 80);

    // "vista" es dónde está la cámara en reposo. Las animaciones (por ejemplo el final)
    // mueven la vista; el parallax del mouse se suma encima en cada cuadro.
    const vista = { posicion: new THREE.Vector3(), objetivo: new THREE.Vector3() };
    // Qué tiene que entrar en cámara: todo, o solo la tirada (ver enfocar()). La vela siempre.
    const aVectores = (puntos) => [
        ...puntos.map(({ x, y, z }) => new THREE.Vector3(x, y, z)),
        VELA.clone(),
        VELA.clone().setY(0.9),
    ];
    const todos = aVectores(puntosDeEncuadre());
    let puntos = todos;

    // --- Luces ---
    const ambiente = new THREE.HemisphereLight('#8C7BC4', '#0E0B1A', 1.1);
    escena.add(ambiente);

    // Luz "de luna" desde arriba: hace legibles las cartas y proyecta las sombras.
    const luna = new THREE.DirectionalLight('#D8D0F0', 1.2);
    luna.position.set(1.5, 9, 4);
    luna.castShadow = true;
    luna.shadow.mapSize.set(2048, 2048);
    Object.assign(luna.shadow.camera, { left: -7, right: 7, top: 7, bottom: -7, near: 1, far: 25 });
    luna.shadow.bias = -0.0004;
    escena.add(luna);

    // Foco sobre la carta que se revela (apagado hasta que haga falta).
    const foco = new THREE.SpotLight('#FFE7B8', 0, 12, Math.PI / 9, 0.6, 1.2);
    foco.position.set(0, 7, 1);
    foco.target.position.set(MAZO.x, 0, MAZO.z);
    escena.add(foco, foco.target);

    // --- Mesa ---
    const mesa = new THREE.Mesh(
        new THREE.PlaneGeometry(22, 16),
        new THREE.MeshStandardMaterial({
            map: crearPano(renderer, { ancho: 22, profundidad: 16, centroCirculo: { x: 0, z: -0.9 }, radioCirculo: 3.05 }),
            roughness: 0.95,
        }),
    );
    mesa.rotation.x = -Math.PI / 2;
    mesa.receiveShadow = true;
    escena.add(mesa);

    // --- Vela ---
    const cera = new THREE.Mesh(
        new THREE.CylinderGeometry(0.16, 0.18, 0.7, 32),
        new THREE.MeshStandardMaterial({ color: '#EFE3C8', roughness: 0.7 }),
    );
    cera.position.copy(VELA).setY(0.35);
    cera.castShadow = true;

    const halo = crearHalo(renderer);
    const llama = new THREE.Sprite(new THREE.SpriteMaterial({ map: halo, blending: THREE.AdditiveBlending, depthWrite: false }));
    llama.position.copy(VELA).setY(0.82);
    llama.scale.set(0.35, 0.55, 1);

    const luzVela = new THREE.PointLight('#FFB36B', 6, 0, 1.4);
    luzVela.position.copy(VELA).setY(1.0);
    escena.add(cera, llama, luzVela);

    // --- Polvo dorado flotando ---
    const CANTIDAD_POLVO = 300;
    const base = new Float32Array(CANTIDAD_POLVO * 3);
    for (let i = 0; i < CANTIDAD_POLVO; i++) {
        base.set([(Math.random() - 0.5) * 12, 0.2 + Math.random() * 3.5, -4.5 + Math.random() * 8.5], i * 3);
    }
    const geometriaPolvo = new THREE.BufferGeometry();
    geometriaPolvo.setAttribute('position', new THREE.BufferAttribute(base.slice(), 3));
    const polvo = new THREE.Points(
        geometriaPolvo,
        new THREE.PointsMaterial({
            map: crearHalo(renderer, '230, 201, 120'),
            size: 0.07,
            transparent: true,
            opacity: 0.7,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
        }),
    );
    escena.add(polvo);

    // --- Tamaño y encuadre ---
    const ajustarTamano = () => {
        const { clientWidth: ancho, clientHeight: alto } = contenedor;
        if (!ancho || !alto) return;
        renderer.setSize(ancho, alto, false);
        camara.aspect = ancho / alto;
        camara.updateProjectionMatrix();
        const encuadre = calcularEncuadre(camara, puntos);
        vista.posicion.copy(encuadre.posicion);
        vista.objetivo.copy(encuadre.objetivo);
    };
    new ResizeObserver(ajustarTamano).observe(contenedor);
    ajustarTamano();

    // --- Parallax suave con el mouse ---
    const quieta = reducirMovimiento();
    const mouse = new THREE.Vector2();
    const desvio = new THREE.Vector3();
    window.addEventListener('pointermove', (e) => {
        mouse.set((e.clientX / window.innerWidth) * 2 - 1, (e.clientY / window.innerHeight) * 2 - 1);
    });

    // --- Loop ---
    const tareas = new Set();
    const posiciones = geometriaPolvo.attributes.position;

    renderer.setAnimationLoop((milisegundos) => {
        const t = milisegundos / 1000;

        if (!quieta) {
            // La llama parpadea con una suma de senos (parece azar, pero es suave).
            const parpadeo = 1 + 0.12 * Math.sin(t * 7.3) + 0.07 * Math.sin(t * 13.1) + 0.05 * Math.sin(t * 2.1);
            luzVela.intensity = 6 * parpadeo;
            llama.scale.set(0.35 * parpadeo, 0.55 * (0.9 + 0.1 * parpadeo), 1);

            for (let i = 0; i < CANTIDAD_POLVO; i++) {
                posiciones.setXYZ(
                    i,
                    base[i * 3] + 0.25 * Math.sin(t * 0.21 + i),
                    base[i * 3 + 1] + 0.18 * Math.sin(t * 0.33 + i * 1.7),
                    base[i * 3 + 2] + 0.25 * Math.cos(t * 0.17 + i * 0.6),
                );
            }
            posiciones.needsUpdate = true;

            desvio.lerp(new THREE.Vector3(mouse.x * 0.35, -mouse.y * 0.2, 0), 0.04);
        }

        camara.position.copy(vista.posicion).add(desvio);
        camara.lookAt(vista.objetivo);

        for (const tarea of tareas) tarea(t);
        renderer.render(escena, camara);
    });

    return {
        /** Encuadre para ver solo estos puntos. No mueve la cámara: devuelve adónde ir, para animarlo. */
        enfocar(lista) {
            puntos = lista === null ? todos : aVectores(lista); // null = la mesa completa (con el abanico)

            return calcularEncuadre(camara, puntos);
        },
        escena,
        camara,
        renderer,
        canvas: renderer.domElement,
        vista,
        luces: { ambiente, luna, foco, vela: luzVela },
        /** Vuelve al encuadre completo, sin animar. */
        reencuadrar() {
            puntos = todos;
            ajustarTamano();
        },
        alCuadro(tarea) {
            tareas.add(tarea);

            return () => tareas.delete(tarea);
        },
    };
}
