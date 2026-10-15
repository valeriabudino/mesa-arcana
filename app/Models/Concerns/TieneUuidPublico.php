<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * El id autoincremental queda interno; hacia afuera (links, canales) se usa un uuid
 * que no permite adivinar ni contar los registros.
 */
trait TieneUuidPublico
{
    use HasUuids;

    /**
     * HasUuids genera el uuid en esta columna (la clave primaria sigue siendo id).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** Las rutas buscan por uuid, no por id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
