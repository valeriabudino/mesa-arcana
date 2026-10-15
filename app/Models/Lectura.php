<?php

namespace App\Models;

use App\Enums\EstadoLectura;
use App\Enums\TipoLectura;
use App\Models\Concerns\TieneMaquinaDeEstados;
use Database\Factories\LecturaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una lectura dentro de una consulta: una tirada de cartas o una carta astral.
 *
 * @property int $id
 * @property int $consulta_id
 * @property TipoLectura $tipo
 * @property EstadoLectura $estado
 * @property string|null $pregunta
 * @property list<int>|null $orden_mazo (solo tiradas)
 * @property array<string, mixed>|null $datos_nacimiento (solo carta astral)
 * @property array<string, mixed>|null $resultado (solo carta astral)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $cerrada_at
 */
#[Fillable(['tipo', 'pregunta'])] // estado, orden_mazo y resultado los manejan solo los servicios
#[Hidden(['orden_mazo'])] // NUNCA se serializa: ni en eventos ni en vistas
class Lectura extends Model
{
    /** @use HasFactory<LecturaFactory> */
    use HasFactory, TieneMaquinaDeEstados;

    protected function casts(): array
    {
        return [
            'tipo' => TipoLectura::class,
            'estado' => EstadoLectura::class,
            'orden_mazo' => 'array',
            'datos_nacimiento' => 'array',
            'resultado' => 'array',
            'cerrada_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Cada tipo arranca en su estado: las tiradas en Lista, la carta astral esperando datos.
        static::creating(function (Lectura $lectura) {
            $lectura->estado ??= $lectura->tipo->estadoInicial();
        });
    }

    /** @return BelongsTo<Consulta, $this> */
    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    /** @return HasMany<LecturaCarta, $this> */
    public function cartas(): HasMany
    {
        return $this->hasMany(LecturaCarta::class)->orderBy('posicion');
    }
}
