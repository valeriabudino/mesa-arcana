<?php

namespace Database\Factories;

use App\Models\Pregunta;
use App\Models\Tema;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pregunta> */
class PreguntaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tema_id' => fn () => Tema::firstOrCreate(['slug' => 'general'], ['nombre' => 'General', 'icono' => '✦', 'orden' => 5])->id,
            'texto' => '¿'.rtrim(fake()->sentence(5), '.').'?',
            'servicios' => ['tres_cartas', 'cruz_celta'],
            'activa' => true,
            'orden' => 0,
        ];
    }

    /** Una pregunta de sí o no. */
    public function siNo(): static
    {
        return $this->state(fn () => ['servicios' => ['si_no']]);
    }
}
