import { crearFondo } from './fondo3d.js';

const lienzo = document.getElementById('fondo');
crearFondo(lienzo, { modo: 'carta', frente: lienzo.dataset.frente });
