<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un tema de las preguntas predeterminadas (amor, dinero y trabajo, …).
 *
 * @property int $id
 * @property string $slug
 * @property string $nombre
 * @property string $icono
 * @property int $orden
 */
#[Fillable(['slug', 'nombre', 'icono', 'orden'])]
class Tema extends Model
{
    /** @return HasMany<Pregunta, $this> */
    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class)->orderBy('orden');
    }

    /**
     * Lo que necesita el formulario de pedido: los temas con sus preguntas activas.
     *
     * @return list<array{slug: string, nombre: string, icono: string, preguntas: list<array{id: int, texto: string, servicios: list<string>}>}>
     */
    public static function paraElFormulario(): array
    {
        return static::query()
            ->orderBy('orden')
            ->with(['preguntas' => fn ($q) => $q->where('activa', true)])
            ->get()
            ->map(fn (Tema $t) => [
                'slug' => $t->slug,
                'nombre' => $t->nombre,
                'icono' => $t->icono,
                'preguntas' => $t->preguntas->map(fn (Pregunta $p) => ['id' => $p->id, 'texto' => $p->texto, 'servicios' => $p->servicios])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
