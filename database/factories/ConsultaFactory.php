<?php

namespace Database\Factories;

use App\Enums\EstadoConsulta;
use App\Models\Consulta;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una consulta activa, con su turno ya tomado por la misma tarotista.
 *
 * @extends Factory<Consulta>
 */
class ConsultaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tarotista_id' => User::factory()->tarotista(),
            'consultante_id' => User::factory()->invitada(),
            'turno_id' => fn (array $atributos) => Turno::factory()
                ->tomado(User::find($atributos['tarotista_id']))
                ->create(['consultante_id' => $atributos['consultante_id']]),
            'estado' => EstadoConsulta::Activa,
        ];
    }
}
