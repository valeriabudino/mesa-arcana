<?php

namespace Database\Factories;

use App\Enums\EstadoTurno;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turno>
 */
class TurnoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'consultante_id' => User::factory()->invitada(),
            'motivo' => fake()->optional()->sentence(),
            'estado' => EstadoTurno::Esperando,
        ];
    }

    public function tomado(?User $tarotista = null): static
    {
        return $this->state(fn () => [
            'estado' => EstadoTurno::Tomado,
            'tarotista_id' => $tarotista?->id ?? User::factory()->tarotista(),
            'tomado_at' => now(),
        ]);
    }
}
