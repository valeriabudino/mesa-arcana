<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lugar de nacimiento para la carta astral. La zona horaria IANA permite convertir
 * la hora local a UTC respetando los cambios de horario históricos de Argentina.
 *
 * @property int $id
 * @property string $nombre
 * @property string $provincia
 * @property float $lat
 * @property float $lng
 * @property string $zona_horaria
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Ciudad extends Model // sin fillable: la carga solo CiudadSeeder
{
    protected $table = 'ciudades';

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }
}
