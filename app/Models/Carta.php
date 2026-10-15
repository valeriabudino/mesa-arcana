<?php

namespace App\Models;

use App\Enums\Arcano;
use App\Enums\Palo;
use App\Enums\SiNo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Catálogo fijo de las 78 cartas del mazo Rider-Waite-Smith.
 *
 * @property int $id
 * @property int $numero
 * @property string $nombre
 * @property Arcano $arcano
 * @property Palo|null $palo
 * @property string $imagen
 * @property string $significado_derecho
 * @property string $significado_invertido
 * @property list<string> $palabras_clave
 * @property SiNo $si_no
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Carta extends Model
{
    protected $table = 'cartas';

    protected function casts(): array
    {
        return [
            'arcano' => Arcano::class,
            'palo' => Palo::class,
            'palabras_clave' => 'array',
            'si_no' => SiNo::class,
        ];
    }

    public function urlImagen(): string
    {
        return asset($this->imagen);
    }
}
