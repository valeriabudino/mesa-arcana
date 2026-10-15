// Dónde va cada cosa sobre la mesa. Solo números: no conoce Three.js ni Echo.
//
// Ejes: la mesa es el plano XZ (y = altura). La cámara mira desde +z,
// así que z negativo es "el fondo" y z positivo "el borde de adelante".
// Cada lugar es { x, z, y?, rotY? }; rotY gira la carta sobre la mesa.

export const CARTA = { ancho: 0.7, alto: 1.2, grosor: 0.003 };

// Separación vertical entre cartas apiladas: un poco más que el grosor
// para que dos caras nunca queden en el mismo plano (z-fighting).
export const PASO_PILA = 0.0035;

export const TOTAL_CARTAS = 78;

// Mazo al empezar y durante la mezcla.
export const MAZO = { x: 0, z: -0.9 };

// Adonde vuelve lo que sobró del abanico cuando ya se eligieron todas.
export const DESCARTE = { x: 3.4, z: -2.5, rotY: -0.2 };

// Las tres pilas de la mezcla (riffle): una fila detrás del mazo, para que al
// intercalarse las cartas viajen hacia adelante sin atravesar ninguna pila.
export const PILAS_MEZCLA = [
    { x: MAZO.x - 1.2, z: MAZO.z - 1.4 },
    { x: MAZO.x, z: MAZO.z - 1.4 },
    { x: MAZO.x + 1.2, z: MAZO.z - 1.4 },
];

// Abanico: arco de círculo con centro detrás de la cámara.
const ABANICO = { cz: 13.6, radio: 11, apertura: (20 * Math.PI) / 180 };

export function lugarEnAbanico(indice, total = TOTAL_CARTAS) {
    const t = total === 1 ? 0.5 : indice / (total - 1);
    const angulo = -ABANICO.apertura + 2 * ABANICO.apertura * t;

    return {
        x: ABANICO.radio * Math.sin(angulo),
        z: ABANICO.cz - ABANICO.radio * Math.cos(angulo),
        y: indice * PASO_PILA, // cada carta apoya sobre la anterior
        rotY: -angulo, // la parte de arriba de la carta apunta hacia afuera del arco
    };
}

// Posiciones de cada tirada, en el mismo orden que TipoTirada::posiciones() en PHP.
const CENTRO = { x: 0, z: -0.9 };
const CRUZ = { x: -0.9, z: -0.9, vertical: 1.45, horizontal: 1.25 };
const BASTON = { x: 1.6, paso: 1.28 };

export const TIRADAS = {
    si_no: [{ ...CENTRO }],

    tres_cartas: [
        { x: CENTRO.x - 1.15, z: CENTRO.z }, // Pasado
        { x: CENTRO.x, z: CENTRO.z }, // Presente
        { x: CENTRO.x + 1.15, z: CENTRO.z }, // Futuro
    ],

    cruz_celta: [
        { x: CRUZ.x, z: CRUZ.z }, // Situación actual
        { x: CRUZ.x, z: CRUZ.z, y: 0.01, rotY: Math.PI / 2 }, // Desafío: cruza a la primera
        { x: CRUZ.x, z: CRUZ.z + CRUZ.vertical }, // Raíz (abajo)
        { x: CRUZ.x - CRUZ.horizontal, z: CRUZ.z }, // Pasado reciente (izquierda)
        { x: CRUZ.x, z: CRUZ.z - CRUZ.vertical }, // Meta (arriba)
        { x: CRUZ.x + CRUZ.horizontal, z: CRUZ.z }, // Futuro cercano (derecha)
        // El "báculo": cuatro cartas en columna, de abajo hacia arriba.
        { x: BASTON.x, z: CRUZ.z + 1.5 * BASTON.paso }, // Una misma
        { x: BASTON.x, z: CRUZ.z + 0.5 * BASTON.paso }, // Entorno
        { x: BASTON.x, z: CRUZ.z - 0.5 * BASTON.paso }, // Esperanzas y miedos
        { x: BASTON.x, z: CRUZ.z - 1.5 * BASTON.paso }, // Resultado
    ],
};

export function lugarEnTirada(tipo, posicion) {
    const lugares = TIRADAS[tipo];

    if (!lugares || !lugares[posicion]) {
        throw new Error(`No hay posición ${posicion} en la tirada "${tipo}"`);
    }

    return { y: 0, rotY: 0, ...lugares[posicion] };
}

// Las cuatro esquinas de una carta apoyada en un lugar (para encuadrar la cámara).
function esquinas({ x, z, y = 0, rotY = 0 }) {
    const c = Math.cos(rotY);
    const s = Math.sin(rotY);

    return [[-1, -1], [1, -1], [1, 1], [-1, 1]].map(([dx, dz]) => {
        const lx = (dx * CARTA.ancho) / 2;
        const lz = (dz * CARTA.alto) / 2;

        return { x: x + lx * c + lz * s, y, z: z - lx * s + lz * c };
    });
}

// Todo lo que tiene que entrar en cámara durante la lectura (abanico, tiradas, pilas, descarte).
export function puntosDeEncuadre() {
    return [
        ...Array.from({ length: TOTAL_CARTAS }, (_, i) => lugarEnAbanico(i)),
        ...Object.values(TIRADAS).flat(),
        ...PILAS_MEZCLA,
        DESCARTE,
    ].flatMap(esquinas);
}

// Para revelar alcanza con la tirada y el descarte: la cámara se puede acercar.
export function puntosDeLectura(tipo) {
    return [...TIRADAS[tipo], DESCARTE].flatMap(esquinas);
}
