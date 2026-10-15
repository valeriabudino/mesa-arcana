<?php

/*
|--------------------------------------------------------------------------
| Qué responde cada carta en una tirada de sí o no
|--------------------------------------------------------------------------
|
| Criterio tradicional: las cartas de expansión, logro o unión dicen "sí";
| las de pérdida, conflicto o bloqueo, "no"; las de espera, duda o
| introspección, "quizás". Mismo orden que data/cartas.php.
|
*/

return [

    'mayores' => [
        'si',     // 0  El Loco
        'si',     // 1  El Mago
        'quizas', // 2  La Sacerdotisa
        'si',     // 3  La Emperatriz
        'si',     // 4  El Emperador
        'si',     // 5  El Hierofante
        'si',     // 6  Los Enamorados
        'si',     // 7  El Carro
        'si',     // 8  La Fuerza
        'quizas', // 9  El Ermitaño
        'si',     // 10 La Rueda de la Fortuna
        'quizas', // 11 La Justicia
        'quizas', // 12 El Colgado
        'no',     // 13 La Muerte
        'si',     // 14 La Templanza
        'no',     // 15 El Diablo
        'no',     // 16 La Torre
        'si',     // 17 La Estrella
        'no',     // 18 La Luna
        'si',     // 19 El Sol
        'si',     // 20 El Juicio
        'si',     // 21 El Mundo
    ],

    // As, 2 … 10, Sota, Caballero, Reina, Rey
    'menores' => [
        'bastos' => ['si', 'si', 'si', 'si', 'no', 'si', 'quizas', 'si', 'quizas', 'no', 'si', 'si', 'si', 'si'],
        'copas' => ['si', 'si', 'si', 'quizas', 'no', 'si', 'quizas', 'no', 'si', 'si', 'si', 'si', 'si', 'si'],
        'espadas' => ['si', 'quizas', 'no', 'quizas', 'no', 'quizas', 'no', 'no', 'no', 'no', 'quizas', 'quizas', 'quizas', 'quizas'],
        'oros' => ['si', 'quizas', 'si', 'quizas', 'no', 'si', 'quizas', 'si', 'si', 'si', 'si', 'si', 'si', 'si'],
    ],

];
