<?php

// La billetera de créditos (plan v3 §13.2). Por ahora ficticia: cada persona que entra a
// consultar recibe un regalo. Cuando llegue Mercado Pago (§13.3), el regalo se cambia por la compra.
return [
    // Créditos que recibe cada consultante al entrar (0 = sin regalo).
    'regalo_inicial' => (int) env('CREDITOS_REGALO', 100),

    // Lo que se queda la plataforma de cada lectura cobrada (en %, redondeado hacia abajo).
    'comision' => (int) env('CREDITOS_COMISION', 20),

    // Precio de cada servicio, en créditos (enteros). Las aclaratorias no se cobran:
    // son parte de una lectura que ya se pagó.
    'precios' => [
        'si_no' => 10,
        'tres_cartas' => 20,
        'carta_astral' => 30,
        'cruz_celta' => 40,
    ],
];
