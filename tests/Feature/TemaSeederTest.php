<?php

use App\Models\Pregunta;
use App\Models\Tema;
use Database\Seeders\TemaSeeder;

it('carga los 5 temas del plan con sus preguntas de tirada y de sí o no', function () {
    $this->seed(TemaSeeder::class);

    expect(Tema::orderBy('orden')->pluck('slug')->all())->toBe(['amor', 'dinero_trabajo', 'bienestar', 'crecimiento', 'general'])
        ->and(Pregunta::count())->toBe(18)
        ->and(Pregunta::whereJsonContains('servicios', 'si_no')->count())->toBe(5); // una de sí o no por tema
});

it('se puede correr dos veces sin duplicar', function () {
    $this->seed(TemaSeeder::class);
    $this->seed(TemaSeeder::class);

    expect(Tema::count())->toBe(5)->and(Pregunta::count())->toBe(18);
});

it('el formulario recibe solo las preguntas activas', function () {
    $this->seed(TemaSeeder::class);
    Pregunta::firstWhere('texto', '¿Qué tengo que soltar?')->update(['activa' => false]);

    $textos = collect(Tema::paraElFormulario())->flatMap(fn ($t) => array_column($t['preguntas'], 'texto'));
    expect($textos)->toHaveCount(17)->not->toContain('¿Qué tengo que soltar?');
});
