// Calcula Sol, Luna y Ascendente con astronomy-engine (MIT).
//
// Lo ejecuta Laravel con Process::run([...]) — un array, sin shell — y SOLO con
// datos generados por el servidor (nunca texto que escribió una persona):
//   node calcular.mjs <instante UTC ISO-8601> <latitud> <longitud> <hora conocida: 1|0>
// Devuelve un JSON con longitudes eclípticas tropicales en grados (0 = 0° Aries).

import * as A from 'astronomy-engine';

const [utc, latTexto, lngTexto, horaConocidaTexto] = process.argv.slice(2);
const lat = Number(latTexto);
const lng = Number(lngTexto);
const fecha = new Date(utc);

if (Number.isNaN(fecha.getTime()) || !Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 66 || Math.abs(lng) > 180) {
    console.error('Argumentos inválidos');
    process.exit(2);
}

const normalizar = (grados) => ((grados % 360) + 360) % 360;
const rad = (g) => (g * Math.PI) / 180;
const tiempo = A.MakeTime(fecha);

// Sol y Luna: longitud eclíptica geocéntrica, equinoccio de la fecha (zodíaco tropical).
const sol = A.SunPosition(tiempo).elon;
const luna = A.EclipticGeoMoon(tiempo).lon;

// Ascendente: el grado del zodíaco que asoma por el horizonte este en ese lugar y momento.
//   RAMC = tiempo sidéreo local (en grados); ε = oblicuidad de la eclíptica; φ = latitud.
//   Asc = atan2(cos RAMC, −(sen RAMC · cos ε + tan φ · sen ε))
let ascendente = null;
if (horaConocidaTexto === '1') {
    const siglos = tiempo.tt / 36525; // siglos julianos desde J2000
    const epsilon = rad(23.4392911 - 0.0130042 * siglos);
    const ramc = rad(normalizar(A.SiderealTime(tiempo) * 15 + lng));
    const phi = rad(lat);
    ascendente = normalizar((Math.atan2(Math.cos(ramc), -(Math.sin(ramc) * Math.cos(epsilon) + Math.tan(phi) * Math.sin(epsilon))) * 180) / Math.PI);
}

console.log(JSON.stringify({ sol: normalizar(sol), luna: normalizar(luna), ascendente }));
