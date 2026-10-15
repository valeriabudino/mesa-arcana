<?php

namespace Database\Factories;

use App\Enums\TipoLectura;
use App\Models\Consulta;
use App\Models\Lectura;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una tirada de tres cartas recién iniciada (estado Lista) dentro de una consulta.
 *
 * @extends Factory<Lectura>
 */
class LecturaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'consulta_id' => Consulta::factory(),
            'tipo' => TipoLectura::TresCartas,
        ];
    }

    public function tipo(TipoLectura $tipo): static
    {
        return $this->state(fn () => ['tipo' => $tipo]);
    }
}
