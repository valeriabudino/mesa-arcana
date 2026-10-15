// Lo que adorna la mesa alrededor de la tirada: más velas, cristales, la bola de cristal
// y frascos. Todo hecho con geometrías simples (no hay modelos 3D que descargar).
// Nada de esto se toca ni se mueve con la lectura: queda afuera de la zona de juego
// (abanico, tiradas, pilas de mezcla y descarte; ver layouts.js).

import * as THREE from 'three';
import { crearHalo } from './texturas.js';

const CERA_MARFIL = '#EFE3C8';
const CERA_VIOLETA = '#6B4FA8';
const DORADO = '#C9A54C';

/** Azar con semilla: la mesa se ve igual en las dos pantallas y en cada recarga. */
function azarConSemilla(semilla) {
    let s = semilla;

    return () => ((s = (s * 16807) % 2147483647) / 2147483647);
}

export function crearDecoracion({ escena, renderer }) {
    const grupo = new THREE.Group();
    const halo = crearHalo(renderer);
    const llamas = [];
    const azar = azarConSemilla(11);

    // --- Velas ---
    function vela({ x, z, alto, radio, color }) {
        const cera = new THREE.Mesh(
            new THREE.CylinderGeometry(radio * 0.94, radio, alto, 28),
            new THREE.MeshStandardMaterial({ color, roughness: 0.7 }),
        );
        cera.position.set(x, alto / 2, z);
        cera.castShadow = true;

        // La cera derretida que se junta arriba: un anillito apenas más claro.
        const borde = new THREE.Mesh(
            new THREE.TorusGeometry(radio * 0.82, radio * 0.12, 8, 28),
            new THREE.MeshStandardMaterial({ color: new THREE.Color(color).offsetHSL(0, 0, 0.08), roughness: 0.5 }),
        );
        borde.rotation.x = -Math.PI / 2;
        borde.position.set(x, alto, z);

        const mecha = new THREE.Mesh(
            new THREE.CylinderGeometry(0.008, 0.008, 0.06, 6),
            new THREE.MeshBasicMaterial({ color: '#1A1208' }),
        );
        mecha.position.set(x, alto + 0.03, z);

        const llama = new THREE.Sprite(new THREE.SpriteMaterial({ map: halo, blending: THREE.AdditiveBlending, depthWrite: false }));
        llama.position.set(x, alto + 0.12, z);
        llama.scale.set(0.3, 0.46, 1);
        llamas.push({ llama, fase: azar() * 10 });

        grupo.add(cera, borde, mecha, llama);
    }

    function platito(x, z, radio) {
        const plato = new THREE.Mesh(
            new THREE.CylinderGeometry(radio, radio * 0.9, 0.04, 40),
            new THREE.MeshStandardMaterial({ color: DORADO, roughness: 0.35, metalness: 0.8 }),
        );
        plato.position.set(x, 0.02, z);
        plato.receiveShadow = true;
        grupo.add(plato);
    }

    // Junto a la vela principal (escena.js, en x -3.6 z -2.9): una alta violeta y una baja.
    vela({ x: -4.25, z: -2.55, alto: 1.05, radio: 0.15, color: CERA_VIOLETA });
    vela({ x: -3.05, z: -3.6, alto: 0.42, radio: 0.2, color: CERA_MARFIL });

    // Del otro lado, un grupo de tres sobre un plato dorado.
    platito(5.0, -2.6, 0.62);
    vela({ x: 4.8, z: -2.85, alto: 0.9, radio: 0.15, color: CERA_VIOLETA });
    vela({ x: 5.3, z: -2.4, alto: 0.55, radio: 0.18, color: CERA_MARFIL });
    vela({ x: 4.75, z: -2.25, alto: 0.32, radio: 0.13, color: CERA_VIOLETA });

    const luzDerecha = new THREE.PointLight('#FFB36B', 3.5, 0, 1.4);
    luzDerecha.position.set(5.0, 1.1, -2.55);
    grupo.add(luzDerecha);

    // --- Cristales (amatista y cuarzo) ---
    function punta(material, radio, alto) {
        const geometria = new THREE.CylinderGeometry(radio, radio * 1.05, alto, 6);
        const cuerpo = new THREE.Mesh(geometria, material);
        cuerpo.position.y = alto / 2;
        const tope = new THREE.Mesh(new THREE.ConeGeometry(radio, radio * 1.7, 6), material);
        tope.position.y = alto + radio * 0.85;
        const p = new THREE.Group();
        p.add(cuerpo, tope);
        cuerpo.castShadow = tope.castShadow = true;

        return p;
    }

    function racimo({ x, z, color, brillo, escala = 1, puntas = 8 }) {
        const material = new THREE.MeshStandardMaterial({
            color,
            emissive: brillo,
            emissiveIntensity: 0.35,
            roughness: 0.18,
            metalness: 0.1,
            flatShading: true,
            transparent: true,
            opacity: 0.92,
        });
        const r = new THREE.Group();

        // La piedra madre, oscura y achatada.
        const roca = new THREE.Mesh(
            new THREE.DodecahedronGeometry(0.32, 0),
            new THREE.MeshStandardMaterial({ color: '#2B2140', roughness: 0.9, flatShading: true }),
        );
        roca.scale.set(1.2, 0.45, 1);
        roca.position.y = 0.08;
        roca.castShadow = true;
        r.add(roca);

        for (let i = 0; i < puntas; i++) {
            const radio = 0.05 + azar() * 0.06;
            const p = punta(material, radio, 0.18 + azar() * 0.45);
            const angulo = (i / puntas) * Math.PI * 2 + azar() * 0.5;
            const distancia = i === 0 ? 0 : 0.08 + azar() * 0.2;
            p.position.set(Math.cos(angulo) * distancia, 0.1, Math.sin(angulo) * distancia);
            // Inclinadas hacia afuera, como crecen en la geoda.
            p.rotation.set(Math.sin(angulo) * distancia * 2.2, azar() * Math.PI, -Math.cos(angulo) * distancia * 2.2);
            r.add(p);
        }
        r.position.set(x, 0, z);
        r.scale.setScalar(escala);
        r.rotation.y = azar() * Math.PI;
        grupo.add(r);
    }

    const AMATISTA = { color: '#8A5BD6', brillo: '#3B1F66' };
    const CUARZO = { color: '#E9E4F5', brillo: '#4A3F66' };
    racimo({ x: -5.05, z: -1.15, ...AMATISTA, escala: 1.25, puntas: 9 });
    racimo({ x: -4.55, z: -0.35, ...AMATISTA, escala: 0.7, puntas: 6 });
    racimo({ x: 2.35, z: -3.55, ...CUARZO, escala: 0.85, puntas: 7 });
    racimo({ x: 5.55, z: 1.25, ...AMATISTA, escala: 0.9, puntas: 7 });
    racimo({ x: -5.5, z: 1.6, ...CUARZO, escala: 0.75, puntas: 6 });

    // --- La bola de cristal ---
    const BOLA = { x: 5.0, z: -0.55, radio: 0.48 };
    const dorado = new THREE.MeshStandardMaterial({ color: DORADO, roughness: 0.3, metalness: 0.85 });

    const base = new THREE.Mesh(
        new THREE.LatheGeometry([
            new THREE.Vector2(0.36, 0),
            new THREE.Vector2(0.38, 0.04),
            new THREE.Vector2(0.3, 0.08),
            new THREE.Vector2(0.2, 0.14),
            new THREE.Vector2(0.24, 0.2),
            new THREE.Vector2(0.3, 0.24),
        ], 40),
        dorado,
    );
    base.castShadow = true;
    const aro = new THREE.Mesh(new THREE.TorusGeometry(0.29, 0.025, 10, 40), dorado);
    aro.rotation.x = -Math.PI / 2;
    aro.position.y = 0.24;

    const alturaBola = 0.24 + BOLA.radio * 0.82;
    const bola = new THREE.Mesh(
        new THREE.SphereGeometry(BOLA.radio, 48, 32),
        new THREE.MeshStandardMaterial({
            color: '#B9A6FF',
            emissive: '#4A2F9A',
            emissiveIntensity: 0.45,
            roughness: 0.05,
            metalness: 0.1,
            transparent: true,
            opacity: 0.38,
            depthWrite: false,
        }),
    );
    bola.position.y = alturaBola;

    // Adentro: una niebla violeta y estrellitas que giran despacio.
    const niebla = new THREE.Sprite(new THREE.SpriteMaterial({
        map: crearHalo(renderer, '160, 120, 255'),
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        opacity: 0.8,
    }));
    niebla.position.y = alturaBola;
    niebla.scale.setScalar(BOLA.radio * 1.9);

    const N = 60;
    const estrellas = new Float32Array(N * 3);
    for (let i = 0; i < N; i++) {
        const v = new THREE.Vector3(azar() - 0.5, azar() - 0.5, azar() - 0.5).normalize().multiplyScalar(azar() * BOLA.radio * 0.8);
        estrellas.set([v.x, v.y, v.z], i * 3);
    }
    const geometriaEstrellas = new THREE.BufferGeometry();
    geometriaEstrellas.setAttribute('position', new THREE.BufferAttribute(estrellas, 3));
    const interior = new THREE.Points(geometriaEstrellas, new THREE.PointsMaterial({
        map: crearHalo(renderer, '255, 240, 200'),
        size: 0.05,
        transparent: true,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
    }));
    interior.position.y = alturaBola;

    // Un reflejo fijo arriba a la izquierda: es lo que hace que "parezca vidrio".
    const reflejo = new THREE.Sprite(new THREE.SpriteMaterial({
        map: crearHalo(renderer, '255, 255, 255'),
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        opacity: 0.55,
    }));
    reflejo.position.set(-BOLA.radio * 0.4, alturaBola + BOLA.radio * 0.45, BOLA.radio * 0.6);
    reflejo.scale.set(0.16, 0.11, 1);

    const luzBola = new THREE.PointLight('#8C6BFF', 1.6, 2.5, 1.5);
    luzBola.position.y = alturaBola;

    const conjuntoBola = new THREE.Group();
    conjuntoBola.add(base, aro, niebla, interior, bola, reflejo, luzBola);
    conjuntoBola.position.set(BOLA.x, 0, BOLA.z);
    grupo.add(conjuntoBola);

    // --- Frascos ---
    function frasco({ x, z, alto, radio, liquido }) {
        const f = new THREE.Group();
        const perfil = [
            [0, 0], [radio, 0], [radio * 1.05, alto * 0.1], [radio, alto * 0.6], [radio * 0.4, alto * 0.78],
            [radio * 0.32, alto * 0.95], [radio * 0.38, alto],
        ].map(([px, py]) => new THREE.Vector2(px, py));
        const vidrio = new THREE.Mesh(
            new THREE.LatheGeometry(perfil, 24),
            new THREE.MeshStandardMaterial({ color: '#D8D0F0', roughness: 0.05, transparent: true, opacity: 0.28, depthWrite: false }),
        );
        const contenido = new THREE.Mesh(
            new THREE.CylinderGeometry(radio * 0.9, radio * 0.9, alto * 0.45, 20),
            new THREE.MeshStandardMaterial({ color: liquido, emissive: liquido, emissiveIntensity: 0.25, roughness: 0.3, transparent: true, opacity: 0.85 }),
        );
        contenido.position.y = alto * 0.25;
        const corcho = new THREE.Mesh(
            new THREE.CylinderGeometry(radio * 0.34, radio * 0.3, alto * 0.14, 12),
            new THREE.MeshStandardMaterial({ color: '#7A5A3A', roughness: 0.9 }),
        );
        corcho.position.y = alto * 1.03;
        contenido.castShadow = corcho.castShadow = true;
        f.add(contenido, vidrio, corcho);
        f.position.set(x, 0, z);
        grupo.add(f);
    }

    frasco({ x: -5.55, z: -2.75, alto: 0.7, radio: 0.17, liquido: '#C97B8E' });
    frasco({ x: -6.0, z: -2.2, alto: 0.5, radio: 0.14, liquido: DORADO });
    frasco({ x: 1.55, z: -3.85, alto: 0.55, radio: 0.13, liquido: '#7FB8C9' });

    escena.add(grupo);

    return {
        luces: { derecha: luzDerecha, bola: luzBola },
        /** Cada cuadro: las llamas parpadean y la bola gira por dentro. */
        animar(t) {
            for (const { llama, fase } of llamas) {
                const p = 1 + 0.12 * Math.sin(t * 7.3 + fase) + 0.07 * Math.sin(t * 13.1 + fase * 2);
                llama.scale.set(0.3 * p, 0.46 * (0.9 + 0.1 * p), 1);
            }
            luzDerecha.intensity = 3.5 * (1 + 0.1 * Math.sin(t * 6.1) + 0.06 * Math.sin(t * 11.7));
            interior.rotation.y = t * 0.25;
            interior.rotation.x = Math.sin(t * 0.2) * 0.3;
            niebla.material.opacity = 0.65 + 0.2 * Math.sin(t * 0.8);
            luzBola.intensity = 1.6 + 0.5 * Math.sin(t * 0.8);
        },
    };
}
