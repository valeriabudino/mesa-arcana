// Timelines de GSAP. Cada función recibe el MISMO payload que el evento de Reverb
// correspondiente y devuelve una Promise que se resuelve cuando termina la animación.
// En la Fase 4, mesa.js solo conecta `.listen('.CartaElegida', e => animaciones.elegir(e))`.

import gsap from 'gsap';
import * as THREE from 'three';
import { DESCARTE, lugarDeCarta, lugarEnAbanico, MAZO, PASO_PILA, PILAS_MEZCLA, puntosDeLectura } from './layouts.js';
import { RESALTE } from './mazo.js';
import { reducirMovimiento } from './escena.js';
import { crearTextoGrabado } from './texturas.js';

const SUAVE = 'power2.inOut'; // el plan: lento y con peso, nada rebota
const xyz = ({ x, y, z }) => ({ x, y, z });

/** Generador pseudoaleatorio con semilla (mulberry32): la misma semilla da la misma mezcla en los dos navegadores. */
function generador(semilla) {
    let a = semilla >>> 0;

    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/** Lleva una carta a un lugar haciendo un arco: sube a mitad de camino y baja. */
function volar(tl, carta, destino, inicio, { duracion, altura }) {
    const { x, z, y = 0, rotY = 0 } = destino;
    const pos = carta.pivote.position;
    const techo = Math.max(pos.y, y) + altura;

    tl.to(pos, { x, z, duration: duracion, ease: SUAVE }, inicio);
    tl.to(carta.pivote.rotation, { y: rotY, duration: duracion, ease: SUAVE }, inicio);
    tl.to(pos, { y: techo, duration: duracion / 2, ease: 'power1.out' }, inicio);
    tl.to(pos, { y, duration: duracion / 2, ease: 'power1.in' }, inicio + duracion / 2);
}

export function crearAnimaciones({ escena, mazo, tipoTirada }) {
    const { luces, vista } = escena;
    const lucesIniciales = Object.fromEntries(Object.entries(luces).map(([k, luz]) => [k, luz.intensity]));

    const lugarDe = (carta) => lugarDeCarta(tipoTirada, carta);

    // "SÍ / NO / QUIZÁS" grabado en dorado sobre el paño (solo en el sí o no).
    let grabado = null;
    const quitarGrabado = () => {
        if (!grabado) return;
        escena.escena.remove(grabado);
        grabado.material.map.dispose();
        grabado.material.dispose();
        grabado.geometry.dispose();
        grabado = null;
    };
    const ponerGrabado = (texto, lugar) => {
        quitarGrabado();
        grabado = new THREE.Mesh(
            new THREE.PlaneGeometry(2.4, 0.9),
            new THREE.MeshBasicMaterial({ map: crearTextoGrabado(escena.renderer, texto), transparent: true, opacity: 0, depthWrite: false }),
        );
        grabado.rotation.x = -Math.PI / 2;
        grabado.position.set(lugar.x, 0.004, lugar.z + 1.3);
        escena.escena.add(grabado);

        return grabado.material;
    };

    if (reducirMovimiento()) {
        gsap.globalTimeline.timeScale(40); // casi instantáneo, pero se respeta el orden
    }

    const animaciones = {
        /** MazoMezclado { semilla_animacion } — riffle en tres pilas, dos veces. */
        mezclar({ semilla_animacion }) {
            const azar = generador(semilla_animacion);
            const tl = gsap.timeline();
            let orden = [...mazo.cartas];

            for (let ronda = 0; ronda < 2; ronda++) {
                // Corte en tres bloques de tamaño parecido.
                const corte1 = 22 + Math.floor(azar() * 8);
                const corte2 = corte1 + 22 + Math.floor(azar() * 8);
                const pilas = [orden.slice(0, corte1), orden.slice(corte1, corte2), orden.slice(corte2)];

                // 1) Cada bloque va a su pila, un poco desprolijo.
                const inicioCorte = tl.duration();
                pilas.forEach((pila, p) =>
                    pila.forEach((carta, k) =>
                        volar(tl, carta, { ...PILAS_MEZCLA[p], y: k * PASO_PILA, rotY: (azar() - 0.5) * 0.08 }, inicioCorte, {
                            duracion: 0.45,
                            altura: 0.3,
                        }),
                    ),
                );

                // 2) Se intercalan: cae la carta de abajo de una pila elegida al azar
                //    (con más chance la que tiene más cartas), como en un riffle real.
                const inicioIntercalado = tl.duration();
                const nuevo = [];
                while (nuevo.length < orden.length) {
                    const quedan = pilas.map((pila) => pila.length);
                    let tirada = azar() * quedan.reduce((a, b) => a + b, 0);
                    const p = quedan.findIndex((n) => (tirada -= n) < 0);
                    nuevo.push(pilas[p].shift());
                }
                nuevo.forEach((carta, k) =>
                    volar(tl, carta, { ...MAZO, y: k * PASO_PILA }, inicioIntercalado + k * 0.007, { duracion: 0.32, altura: 0.25 }),
                );
                orden = nuevo;
            }

            // Mismo array (otros módulos lo tienen referenciado), nuevo orden visual.
            mazo.cartas.splice(0, mazo.cartas.length, ...orden);

            return tl.then();
        },

        /** Después de mezclar: las 78 se despliegan en arco. La de arriba de la pila sale primero. */
        abanico() {
            const tl = gsap.timeline();
            const n = mazo.cartas.length;
            mazo.cartas.forEach((carta, i) => volar(tl, carta, lugarEnAbanico(i), (n - 1 - i) * 0.012, { duracion: 0.6, altura: 0.3 }));

            return tl.then(() => mazo.cartas.forEach((carta) => (carta.enAbanico = true)));
        },

        /**
         * CartaElegida { posicion, indice_abanico, aclara } — sale del abanico y vuela a su lugar,
         * boca abajo. Si es una aclaratoria (aclara = posición), va junto a la carta que aclara.
         */
        elegir({ posicion, indice_abanico, aclara = null }) {
            const carta = mazo.cartas[indice_abanico];
            if (!carta?.enAbanico) return Promise.resolve(); // evento repetido o fuera de lugar: se ignora

            carta.enAbanico = false;
            carta.posicion = posicion;
            carta.aclara = aclara;
            mazo.porPosicion.set(posicion, carta);

            const tl = gsap.timeline();
            tl.to(carta.dorso, { emissiveIntensity: 0, duration: 0.3 }, 0);
            tl.to(carta.acostada.position, { y: 0, z: 0, duration: 0.3 }, 0);
            volar(tl, carta, lugarDe(carta), 0, { duracion: 0.9, altura: 1.1 });

            return tl.then();
        },

        /** Ya se eligieron todas: lo que quedó del abanico se junta al costado y la cámara se acerca a la tirada. */
        recogerAbanico() {
            const resto = mazo.cartas.filter((carta) => carta.enAbanico);
            resto.forEach((carta) => (carta.enAbanico = false));

            const tl = gsap.timeline();
            resto.forEach((carta, k) => {
                tl.to(carta.dorso, { emissiveIntensity: 0, duration: 0.2 }, 0);
                tl.to(carta.acostada.position, { y: 0, z: 0, duration: 0.2 }, 0);
                volar(tl, carta, { ...DESCARTE, y: k * PASO_PILA }, k * 0.01, { duracion: 0.5, altura: 0.25 });
            });

            const cerca = escena.enfocar(puntosDeLectura(tipoTirada));
            tl.to(vista.posicion, { ...xyz(cerca.posicion), duration: 1.6, ease: SUAVE }, 0.3);
            tl.to(vista.objetivo, { ...xyz(cerca.objetivo), duration: 1.6, ease: SUAVE }, 0.3);

            return tl.then();
        },

        /**
         * AclaratoriaAceptada — las cartas que quedaron vuelven del costado a su MISMO lugar
         * del abanico (no se remezcla: los huecos muestran las que ya salieron).
         */
        reabrirAbanico() {
            const tl = gsap.timeline();
            const libres = mazo.cartas.map((carta, i) => [carta, i]).filter(([carta]) => carta.posicion === null);
            libres.forEach(([carta, i], k) => volar(tl, carta, lugarEnAbanico(i), k * 0.01, { duracion: 0.6, altura: 0.3 }));

            const toda = escena.enfocar(null);
            tl.to(vista.posicion, { ...xyz(toda.posicion), duration: 1.4, ease: SUAVE }, 0);
            tl.to(vista.objetivo, { ...xyz(toda.objetivo), duration: 1.4, ease: SUAVE }, 0);
            tl.to(luces.foco, { intensity: 0, duration: 0.6 }, 0);

            return tl.then(() => libres.forEach(([carta]) => (carta.enAbanico = true)));
        },

        /**
         * CartaRevelada { posicion, invertida, carta: { imagen, ... } }
         * Recién acá se descarga la imagen del frente; después la carta salta, gira y cae.
         */
        async revelar({ posicion, invertida, carta: datos, respuesta = null }) {
            const carta = mazo.porPosicion.get(posicion);
            if (!carta || carta.revelada) return; // evento repetido o fuera de lugar: se ignora
            carta.revelada = true;

            try {
                mazo.ponerFrente(carta, await mazo.cargarFrente(datos.imagen));
            } catch (error) {
                console.warn('No se pudo cargar el frente de la carta', datos.imagen, error);
            }

            const lugar = lugarDe(carta);
            const tl = gsap.timeline();

            // El foco se mueve hasta la carta y se enciende.
            tl.to(luces.foco.target.position, { x: lugar.x, z: lugar.z, duration: 0.5, ease: SUAVE }, 0);
            tl.to(luces.foco.position, { x: lugar.x, z: lugar.z + 1.5, duration: 0.5, ease: SUAVE }, 0);
            tl.to(luces.foco, { intensity: 40, duration: 0.6 }, 0);

            // Salto, giro sobre su eje largo y, si salió invertida, media vuelta sobre la mesa.
            tl.to(carta.pivote.position, { y: lugar.y + 0.55, duration: 0.35, ease: 'power2.out' }, 0);
            tl.to(carta.volteo.rotation, { y: 0, duration: 0.7, ease: SUAVE }, 0.15);
            if (invertida) {
                tl.to(carta.pivote.rotation, { y: lugar.rotY + Math.PI, duration: 0.7, ease: SUAVE }, 0.15);
            }
            tl.to(carta.pivote.position, { y: lugar.y, duration: 0.35, ease: 'power2.in' }, 0.75);

            // Sí o no: la respuesta (calculada en el servidor) aparece grabada en el paño.
            if (respuesta) tl.to(ponerGrabado(respuesta.texto, lugar), { opacity: 1, duration: 1.2, ease: SUAVE }, 1.1);

            return tl.then();
        },

        /**
         * EstadoCambiado → cerrada: todo vuelve a la pila boca abajo y la cámara a la mesa
         * completa ("reposo": el mazo y la vela, mientras se conversa).
         */
        volverAReposo() {
            const tl = gsap.timeline();
            mazo.cartas.forEach((carta, i) => {
                carta.enAbanico = false;
                tl.to(carta.volteo.rotation, { y: Math.PI, duration: 0.5, ease: SUAVE }, 0);
                tl.to(carta.acostada.position, { y: 0, z: 0, duration: 0.2 }, 0);
                volar(tl, carta, { ...MAZO, y: i * PASO_PILA }, 0.3 + i * 0.006, { duracion: 0.6, altura: 0.35 });
            });
            if (grabado) tl.to(grabado.material, { opacity: 0, duration: 0.6 }, 0);
            tl.to(luces.foco, { intensity: 0, duration: 0.6 }, 0);

            const toda = escena.enfocar(null);
            tl.to(vista.posicion, { ...xyz(toda.posicion), duration: 1.6, ease: SUAVE }, 0.2);
            tl.to(vista.objetivo, { ...xyz(toda.objetivo), duration: 1.6, ease: SUAVE }, 0.2);

            // Al terminar, estado limpio (libera las imágenes de los frentes).
            return tl.then(() => animaciones.reiniciar());
        },

        /** Cierre (con ConsultaFinalizada, en la Fase 3): la cámara se aleja y las reveladas quedan brillando. */
        finalizar() {
            const tl = gsap.timeline();
            const lejos = vista.posicion.clone().sub(vista.objetivo).multiplyScalar(1.15).add(vista.objetivo);

            tl.to(vista.posicion, { ...xyz(lejos), duration: 2.4, ease: SUAVE }, 0);
            tl.to(luces.ambiente, { intensity: lucesIniciales.ambiente * 0.45, duration: 2 }, 0);
            tl.to(luces.luna, { intensity: lucesIniciales.luna * 0.4, duration: 2 }, 0);
            tl.to(luces.foco, { intensity: 0, duration: 1 }, 0);
            for (const carta of mazo.porPosicion.values()) {
                if (carta.frente) tl.to(carta.frente, { emissiveIntensity: 0.3, duration: 2 }, 0.3);
            }

            return tl.then();
        },

        /** Hover: la carta sube y brilla. tipo 'propio' (dorado) o 'remoto' (rosado, el whisper de la Fase 4). */
        resaltar(indice, tipo, activo) {
            const carta = mazo.cartas[indice];
            if (!carta?.enAbanico) return;

            if (activo) carta.dorso.emissive.copy(RESALTE[tipo]);
            gsap.to(carta.dorso, { emissiveIntensity: activo ? 0.22 : 0, duration: 0.2, overwrite: 'auto' });
            gsap.to(carta.acostada.position, { y: activo ? 0.1 : 0, z: activo ? -0.12 : 0, duration: 0.2, overwrite: 'auto' });
        },

        /**
         * Dibuja de golpe la lectura de GET /c/{uuid}/estado (al entrar o al reconectar).
         * Las cartas sin revelar llegan solo con posicion e indice_abanico, igual que en CartaElegida.
         */
        async dibujar({ tipo, estado, cartas }) {
            animaciones.reiniciar();
            animaciones.usarTirada(tipo);
            if (estado === 'lista') return; // el mazo quieto en el centro

            // El abanico completo y, encima, las elegidas en su lugar de la tirada.
            mazo.cartas.forEach((carta, i) => {
                mazo.colocar(carta, lugarEnAbanico(i));
                carta.enAbanico = true;
            });
            for (const elegida of cartas) {
                const carta = mazo.cartas[elegida.indice_abanico];
                carta.enAbanico = false;
                carta.posicion = elegida.posicion;
                carta.aclara = elegida.aclara ?? null;
                mazo.porPosicion.set(elegida.posicion, carta);
                mazo.colocar(carta, lugarDe(carta));
            }

            if (estado === 'revelando' || estado === 'abierta') {
                mazo.cartas
                    .filter((carta) => carta.enAbanico)
                    .forEach((carta, k) => {
                        carta.enAbanico = false;
                        mazo.colocar(carta, { ...DESCARTE, y: k * PASO_PILA });
                    });
                const cerca = escena.enfocar(puntosDeLectura(tipoTirada));
                vista.posicion.copy(cerca.posicion);
                vista.objetivo.copy(cerca.objetivo);
            }

            await Promise.all(
                cartas
                    .filter((elegida) => elegida.revelada)
                    .map(async (elegida) => {
                        const carta = mazo.porPosicion.get(elegida.posicion);
                        carta.revelada = true;
                        try {
                            mazo.ponerFrente(carta, await mazo.cargarFrente(elegida.carta.imagen));
                        } catch (error) {
                            console.warn('No se pudo cargar el frente de la carta', elegida.carta.imagen, error);
                        }
                        carta.volteo.rotation.y = 0;
                        if (elegida.invertida) carta.pivote.rotation.y += Math.PI;
                        if (elegida.respuesta) ponerGrabado(elegida.respuesta.texto, lugarDe(carta)).opacity = 1;
                    }),
            );
        },

        /** Cambia el tipo de tirada (cada lectura de la consulta puede ser de otro tipo). */
        usarTirada(tipo) {
            tipoTirada = tipo;
        },

        /** Vuelve todo al principio sin animar (para el laboratorio y al redibujar desde /estado). */
        reiniciar() {
            gsap.globalTimeline.clear();
            quitarGrabado();
            mazo.reiniciar();
            for (const [k, luz] of Object.entries(luces)) luz.intensity = lucesIniciales[k];
            escena.reencuadrar();
        },
    };

    return animaciones;
}
