<?php

namespace Database\Seeders;

use App\Enums\Arcano;
use App\Enums\Palo;
use App\Enums\SiNo;
use App\Models\Carta;
use Illuminate\Database\Seeder;

class CartaSeeder extends Seeder
{
    /**
     * Carga las 78 cartas desde data/cartas.php (+ su respuesta de sí/no desde data/si_no.php).
     * Es idempotente: se puede correr varias veces sin duplicar.
     */
    public function run(): void
    {
        $datos = require __DIR__.'/data/cartas.php';
        $siNo = require __DIR__.'/data/si_no.php';

        foreach ($datos['mayores'] as $numero => $carta) {
            $this->guardar($carta, Arcano::Mayor, null, $numero, sprintf('cartas/mayor-%02d.webp', $numero), SiNo::from($siNo['mayores'][$numero]));
        }

        foreach ($datos['menores'] as $palo => $cartas) {
            foreach ($cartas as $indice => $carta) {
                $numero = $indice + 1;
                $this->guardar($carta, Arcano::Menor, Palo::from($palo), $numero, sprintf('cartas/%s-%02d.webp', $palo, $numero), SiNo::from($siNo['menores'][$palo][$indice]));
            }
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: list<string>}  $carta
     */
    private function guardar(array $carta, Arcano $arcano, ?Palo $palo, int $numero, string $imagen, SiNo $siNo): void
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
                'si_no' => $siNo,
            ],
        ));
    }
}
