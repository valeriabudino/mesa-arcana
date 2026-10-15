// Las 78 cartas de la escena. Son ANÓNIMAS: la escena no sabe qué carta es cuál.
// Se identifican por su lugar en el abanico (indice_abanico) y, cuando se eligen,
// por su posición en la tirada. El frente recién se pone cuando llega CartaRevelada.

import * as THREE from 'three';
import { CARTA, MAZO, PASO_PILA, TOTAL_CARTAS } from './layouts.js';
import { crearDorso } from './texturas.js';

// Colores de resaltado: el propio (dorado) y el de la otra persona (rosado del plan).
export const RESALTE = { propio: new THREE.Color('#C9A54C'), remoto: new THREE.Color('#C97B8E') };

const cargador = new THREE.TextureLoader();

export function crearMazo({ escena, renderer }) {
    // Caras de la caja: [+x, -x, +y, -y, +z, -z] → los cuatro cantos, el frente (+z) y el dorso (-z).
    const geometria = new THREE.BoxGeometry(CARTA.ancho, CARTA.alto, CARTA.grosor);
    const texturaDorso = crearDorso(renderer);
    const canto = new THREE.MeshStandardMaterial({ color: '#D8C79A', roughness: 0.6 });
    const frenteVacio = new THREE.MeshStandardMaterial({ color: '#F3EBDD', roughness: 0.6 });

    const cartas = Array.from({ length: TOTAL_CARTAS }, () => {
        // pivote: dónde está sobre la mesa y hacia dónde apunta (rotation.y). Acá va la invertida.
        const pivote = new THREE.Object3D();
        // acostada: la apoya sobre la mesa (la parte de arriba de la imagen apunta al fondo).
        // Su posición se usa para el "hover" (levantarla un poquito) sin tocar al pivote.
        const acostada = new THREE.Object3D();
        acostada.rotation.x = -Math.PI / 2;
        // volteo: rotation.y = π es boca abajo; 0 es boca arriba.
        const dorso = new THREE.MeshStandardMaterial({
            map: texturaDorso,
            roughness: 0.5,
            metalness: 0.1,
            emissive: RESALTE.propio.clone(),
            emissiveIntensity: 0,
        });
        const volteo = new THREE.Mesh(geometria, [canto, canto, canto, canto, frenteVacio, dorso]);
        volteo.castShadow = true;
        volteo.receiveShadow = true;

        pivote.add(acostada);
        acostada.add(volteo);
        escena.add(pivote);

        const carta = { pivote, acostada, volteo, dorso, frente: null, enAbanico: false, posicion: null, revelada: false };
        volteo.userData.carta = carta; // para saber qué carta tocó el raycaster

        return carta;
    });

    const mazo = {
        // cartas[i] es la carta que ocupa el lugar i de la pila (y después del abanico).
        cartas,
        porPosicion: new Map(),

        colocar(carta, { x, z, y = 0, rotY = 0 }) {
            carta.pivote.position.set(x, y, z);
            carta.pivote.rotation.y = rotY;
        },

        /** Todo vuelve a una pila boca abajo en el centro, sin animación. */
        reiniciar() {
            mazo.porPosicion.clear();
            cartas.forEach((carta, i) => {
                mazo.colocar(carta, { x: MAZO.x, z: MAZO.z, y: i * PASO_PILA });
                carta.volteo.rotation.y = Math.PI;
                carta.acostada.position.set(0, 0, 0);
                carta.dorso.emissiveIntensity = 0;
                carta.enAbanico = false;
                carta.posicion = null;
                carta.revelada = false;
                if (carta.frente) {
                    carta.frente.map?.dispose();
                    carta.frente.dispose();
                    carta.frente = null;
                    carta.volteo.material[4] = frenteVacio;
                }
            });
        },

        /** Descarga la imagen del frente. Se llama recién al revelar. */
        async cargarFrente(url) {
            const textura = await cargador.loadAsync(url);
            textura.colorSpace = THREE.SRGBColorSpace;
            textura.anisotropy = renderer.capabilities.getMaxAnisotropy();

            return textura;
        },

        ponerFrente(carta, textura) {
            carta.frente = new THREE.MeshStandardMaterial({
                map: textura,
                roughness: 0.55,
                emissive: '#ffffff',
                emissiveMap: textura, // para que brille "desde adentro" en el final
                emissiveIntensity: 0,
            });
            carta.volteo.material[4] = carta.frente;
        },

        /** Las mallas que el raycaster puede tocar: solo las del abanico. */
        seleccionables() {
            return cartas.filter((c) => c.enAbanico).map((c) => c.volteo);
        },
    };

    mazo.reiniciar();

    return mazo;
}
