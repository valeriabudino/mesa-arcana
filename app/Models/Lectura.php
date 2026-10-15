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
 * @property int|null $pregunta_id
 * @property-read Pregunta|null $pregunta
 * @property int $cartas_esperadas (las de la tirada + 1 por cada aclaratoria aceptada)
 * @property int|null $aclarando (posición que se está aclarando mientras se elige)
 * @property list<int>|null $orden_mazo (solo tiradas)
 * @property array<string, mixed>|null $datos_nacimiento (solo carta astral)
 * @property array<string, mixed>|null $resultado (solo carta astral)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $cerrada_at
 */
#[Fillable(['tipo', 'pregunta_id'])] // estado, orden_mazo y resultado los manejan solo los servicios
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
            $lectura->cartas_esperadas = $lectura->tipo->cantidad();
        });
    }

    /** @return BelongsTo<Pregunta, $this> */
    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class);
    }

    /** @return BelongsTo<Consulta, $this> */
    public function consulta(): BelongsTo
    {
        return $this->belongsTo(Consulta::class);
    }

    /**
     * Lo público de una lectura (sin cartas): lo usan LecturaIniciada y GET /estado.
     *
     * @return array{id: int, tipo: string, estado: string, pregunta: string|null, cartas_esperadas: int}
     */
    public function datosPublicos(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'estado' => $this->estado->value,
            'pregunta' => $this->pregunta?->texto,
            'cartas_esperadas' => $this->cartas_esperadas, // cuántas se eligen (la tirada + aclaratorias)
        ];
    }

    /** ¿Ya terminó? Todas las cartas a la vista, o la carta astral calculada. Se puede pedir otra. */
    public function terminada(): bool
    {
        return $this->estado === EstadoLectura::Abierta || $this->estado === EstadoLectura::Calculada;
    }

    /**
     * ¿Se puede pedir una carta aclaratoria para la carta de esta posición?
     * Devuelve el motivo si no, o null si sí. (Lo usan al pedir y al aceptar.)
     */
    public function motivoParaNoAclarar(int $posicion): ?string
    {
        if (! $this->tipo->admiteAclaratorias()) {
            return 'En esta lectura no se piden aclaratorias.';
        }
        if ($this->estado !== EstadoLectura::Abierta) {
            return 'Las aclaratorias se piden con todas las cartas reveladas.';
        }

        $objetivo = $this->cartas()->where('posicion', $posicion)->first();
        if ($objetivo === null || $objetivo->es_aclaratoria) {
            return 'Esa carta no se puede aclarar.';
        }
        if ($this->cartas()->where('aclara_posicion', $posicion)->exists()) {
            return 'Esa carta ya tiene su aclaratoria.';
        }

        return null;
    }

    /** @return HasMany<LecturaCarta, $this> */
    public function cartas(): HasMany
    {
        return $this->hasMany(LecturaCarta::class)->orderBy('posicion');
    }
}
