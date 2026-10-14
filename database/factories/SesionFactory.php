<?php

namespace Database\Factories;

use App\Enums\EstadoSesion;
use App\Enums\TipoTirada;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sesion>
 */
class SesionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tarotista_id' => User::factory()->tarotista(),
            'tipo_tirada' => TipoTirada::TresCartas,
            'pregunta' => fake()->optional()->sentence(),
            'estado' => EstadoSesion::Esperando,
        ];
    }

    /** Con la consultante ya asociada (estado Lista). */
    public function conConsultante(?User $consultante = null): static
    {
        return $this->state(fn (array $attributes) => [
            'consultante_id' => $consultante?->id ?? User::factory()->consultante(),
            'estado' => EstadoSesion::Lista,
        ]);
    }
}
