<?php

// Interpretación de las lecturas (plan v3 §13.6).
return [
    // ia: la escribe un modelo (con la base de respaldo si falla) · base: solo los significados de la base.
    'interpretador' => env('INTERPRETACION', 'ia'),

    // Proveedor y modelo de Prism. Groq es gratis para desarrollo (la clave va en GROQ_API_KEY).
    'proveedor' => env('INTERPRETACION_PROVEEDOR', 'groq'),
    'modelo' => env('INTERPRETACION_MODELO', 'openai/gpt-oss-120b'),

    // Si la IA no terminó en este tiempo, se usa la base.
    'timeout' => (int) env('INTERPRETACION_TIMEOUT', 20),

    // Cada cuánto se le manda a la tarotista el borrador que se va escribiendo.
    'intervalo_ms' => 300,

    // Las interpretaciones de la IA se guardan por pregunta + cartas + orientaciones.
    'cache_dias' => 30,
];
