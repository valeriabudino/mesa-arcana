<?php

namespace Database\Factories;

use App\Enums\TipoMensaje;
use App\Models\Consulta;
use App\Models\Mensaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un mensaje de texto de la consultante de la consulta.
 *
 * @extends Factory<Mensaje>
 */
class MensajeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'consulta_id' => Consulta::factory(),
            'user_id' => fn (array $atributos) => Consulta::find($atributos['consulta_id'])->consultante_id,
            'tipo' => TipoMensaje::Texto,
            'contenido' => fake()->sentence(),
        ];
    }
}
