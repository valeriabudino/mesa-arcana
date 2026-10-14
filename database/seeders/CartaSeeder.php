<?php

namespace Database\Seeders;

use App\Enums\Arcano;
use App\Enums\Palo;
use App\Models\Carta;
use Illuminate\Database\Seeder;

class CartaSeeder extends Seeder
{
    /**
     * Carga las 78 cartas desde data/cartas.php.
     * Es idempotente: se puede correr varias veces sin duplicar.
     */
    public function run(): void
    {
        $datos = require __DIR__.'/data/cartas.php';

        foreach ($datos['mayores'] as $numero => $carta) {
            $this->guardar($carta, Arcano::Mayor, null, $numero, sprintf('cartas/mayor-%02d.webp', $numero));
        }

        foreach ($datos['menores'] as $palo => $cartas) {
            foreach ($cartas as $indice => $carta) {
                $numero = $indice + 1;
                $this->guardar($carta, Arcano::Menor, Palo::from($palo), $numero, sprintf('cartas/%s-%02d.webp', $palo, $numero));
            }
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: list<string>}  $carta
     */
    private function guardar(array $carta, Arcano $arcano, ?Palo $palo, int $numero, string $imagen): void
    {
        [$nombre, $derecho, $invertido, $claves] = $carta;

        Carta::unguarded(fn () => Carta::updateOrCreate(
            ['nombre' => $nombre],
            [
                'numero' => $numero,
                'arcano' => $arcano,
                'palo' => $palo,
                'imagen' => $imagen,
                'significado_derecho' => $derecho,
                'significado_invertido' => $invertido,
                'palabras_clave' => $claves,
            ],
        ));
    }
}
