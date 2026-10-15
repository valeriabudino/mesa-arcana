<?php

namespace Database\Seeders;

use App\Models\Ciudad;
use Illuminate\Database\Seeder;

class CiudadSeeder extends Seeder
{
    /** Carga las ciudades desde data/ciudades.php. Idempotente. */
    public function run(): void
    {
        foreach (require __DIR__.'/data/ciudades.php' as [$nombre, $provincia, $lat, $lng, $zona]) {
            Ciudad::unguarded(fn () => Ciudad::updateOrCreate(
                ['nombre' => $nombre, 'provincia' => $provincia],
                ['lat' => $lat, 'lng' => $lng, 'zona_horaria' => $zona],
            ));
        }
    }
}
