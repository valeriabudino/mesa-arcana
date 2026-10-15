<?php

namespace App\Models;

use App\Enums\TipoLectura;
use Database\Factories\PreguntaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una pregunta predeterminada (plan v3 §13.1). La consultante no escribe la pregunta:
 * la elige de esta lista, filtrada por el servicio que pide.
 *
 * @property int $id
 * @property int $tema_id
 * @property string $texto
 * @property list<string> $servicios
 * @property bool $activa
 * @property int $orden
 * @property-read Tema $tema
 */
#[Fillable(['texto', 'servicios', 'activa', 'orden'])]
class Pregunta extends Model
{
    /** @use HasFactory<PreguntaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['servicios' => 'array', 'activa' => 'boolean'];
    }

    /** @return BelongsTo<Tema, $this> */
    public function tema(): BelongsTo
    {
        return $this->belongsTo(Tema::class);
    }

    /** ¿Se puede elegir para este servicio? (Activa y del tipo correcto: tirada o sí/no.) */
    public function sirvePara(TipoLectura $servicio): bool
    {
        return $this->activa && in_array($servicio->value, $this->servicios, true);
    }

    /** @return array{id: int, texto: string, tema: array{nombre: string, icono: string}} */
    public function datosPublicos(): array
    {
        return ['id' => $this->id, 'texto' => $this->texto, 'tema' => ['nombre' => $this->tema->nombre, 'icono' => $this->tema->icono]];
    }
}
