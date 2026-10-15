<?php

/*
|--------------------------------------------------------------------------
| Ciudades de nacimiento para la carta astral
|--------------------------------------------------------------------------
|
| Capitales de provincia y algunas ciudades grandes. La zona horaria es la
| de la base IANA (tzdata): Argentina tiene varias porque cada provincia
| cambió de horario en fechas distintas a lo largo de la historia, y eso
| importa para convertir la hora de nacimiento a UTC.
|
| Formato: [nombre, provincia, latitud, longitud, zona horaria]
|
*/

return [
    ['Ciudad Autónoma de Buenos Aires', 'CABA', -34.60372, -58.38159, 'America/Argentina/Buenos_Aires'],
    ['La Plata', 'Buenos Aires', -34.92145, -57.95453, 'America/Argentina/Buenos_Aires'],
    ['Mar del Plata', 'Buenos Aires', -38.00548, -57.54261, 'America/Argentina/Buenos_Aires'],
    ['Bahía Blanca', 'Buenos Aires', -38.71834, -62.26634, 'America/Argentina/Buenos_Aires'],
    ['Córdoba', 'Córdoba', -31.42008, -64.18878, 'America/Argentina/Cordoba'],
    ['Río Cuarto', 'Córdoba', -33.12417, -64.34926, 'America/Argentina/Cordoba'],
    ['Rosario', 'Santa Fe', -32.94424, -60.65054, 'America/Argentina/Cordoba'],
    ['Santa Fe', 'Santa Fe', -31.61066, -60.69730, 'America/Argentina/Cordoba'],
    ['Paraná', 'Entre Ríos', -31.73197, -60.52380, 'America/Argentina/Cordoba'],
    ['Corrientes', 'Corrientes', -27.46924, -58.83063, 'America/Argentina/Cordoba'],
    ['Posadas', 'Misiones', -27.36708, -55.89608, 'America/Argentina/Cordoba'],
    ['Resistencia', 'Chaco', -27.46056, -58.98389, 'America/Argentina/Cordoba'],
    ['Formosa', 'Formosa', -26.17753, -58.17814, 'America/Argentina/Cordoba'],
    ['Clorinda', 'Formosa', -25.28481, -57.71851, 'America/Argentina/Cordoba'],
    ['Santiago del Estero', 'Santiago del Estero', -27.79511, -64.26149, 'America/Argentina/Cordoba'],
    ['San Miguel de Tucumán', 'Tucumán', -26.80829, -65.21759, 'America/Argentina/Tucuman'],
    ['Salta', 'Salta', -24.78212, -65.42319, 'America/Argentina/Salta'],
    ['San Salvador de Jujuy', 'Jujuy', -24.18578, -65.29948, 'America/Argentina/Jujuy'],
    ['San Fernando del Valle de Catamarca', 'Catamarca', -28.46957, -65.78524, 'America/Argentina/Catamarca'],
    ['La Rioja', 'La Rioja', -29.41314, -66.85584, 'America/Argentina/La_Rioja'],
    ['San Juan', 'San Juan', -31.53750, -68.53639, 'America/Argentina/San_Juan'],
    ['Mendoza', 'Mendoza', -32.88946, -68.84584, 'America/Argentina/Mendoza'],
    ['San Luis', 'San Luis', -33.29501, -66.33563, 'America/Argentina/San_Luis'],
    ['Santa Rosa', 'La Pampa', -36.61667, -64.28333, 'America/Argentina/Salta'],
    ['Neuquén', 'Neuquén', -38.95161, -68.05910, 'America/Argentina/Salta'],
    ['Viedma', 'Río Negro', -40.81345, -62.99668, 'America/Argentina/Salta'],
    ['San Carlos de Bariloche', 'Río Negro', -41.13348, -71.31030, 'America/Argentina/Salta'],
    ['Rawson', 'Chubut', -43.30016, -65.10228, 'America/Argentina/Catamarca'],
    ['Comodoro Rivadavia', 'Chubut', -45.86413, -67.49656, 'America/Argentina/Catamarca'],
    ['Río Gallegos', 'Santa Cruz', -51.62261, -69.21813, 'America/Argentina/Rio_Gallegos'],
    ['Ushuaia', 'Tierra del Fuego', -54.80191, -68.30295, 'America/Argentina/Ushuaia'],
];
