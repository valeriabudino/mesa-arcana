<?php

namespace App\Models;

use App\Enums\EstadoSesion;
use App\Enums\TipoTirada;
use App\Exceptions\AccionFueraDeOrden;
use Database\Factories\SesionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una lectura entre una tarotista y una consultante.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tarotista_id
 * @property int|null $consultante_id
 * @property TipoTirada $tipo_tirada
 * @property string|null $pregunta
 * @property EstadoSesion $estado
 * @property list<int>|null $orden_mazo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $finalizada_at
 */
#[Fillable(['tipo_tirada', 'pregunta'])] // estado, consultante y orden_mazo los maneja solo LecturaService
#[Hidden(['orden_mazo'])] // NUNCA se serializa: ni en eventos ni en vistas
class Sesion extends Model
{
    /** @use HasFactory<SesionFactory> */
    use HasFactory, HasUuids;

    protected $table = 'sesiones';

    protected $attributes = [
        'estado' => 'esperando',
    ];

    protected function casts(): array
    {
        return [
            'tipo_tirada' => TipoTirada::class,
            'estado' => EstadoSesion::class,
            'orden_mazo' => 'array',
            'finalizada_at' => 'datetime',
        ];
    }

    /**
     * HasUuids genera el uuid en esta columna (la clave primaria sigue siendo id).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** Las rutas /s/{sesion} buscan por uuid, no por id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function tarotista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tarotista_id');
    }

    /** @return BelongsTo<User, $this> */
    public function consultante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultante_id');
    }

    /** @return HasMany<SesionCarta, $this> */
    public function cartas(): HasMany
    {
        return $this->hasMany(SesionCarta::class)->orderBy('posicion');
    }

    /**
     * Único lugar donde cambia el estado: valida contra la máquina de estados.
     * No guarda; lo hace quien llama (LecturaService, dentro de su transacción).
     *
     * @throws AccionFueraDeOrden
     */
    public function pasarA(EstadoSesion $nuevo): void
    {
        if (! $this->estado->puedePasarA($nuevo)) {
            throw AccionFueraDeOrden::transicion($this->estado, $nuevo);
        }

        $this->estado = $nuevo;
    }

    /**
     * Para acciones que no cambian de estado (elegir, revelar): exige estar en uno concreto.
     *
     * @throws AccionFueraDeOrden
     */
    public function exigirEstado(EstadoSesion $esperado): void
    {
        if ($this->estado !== $esperado) {
            throw new AccionFueraDeOrden("Esto se puede hacer solo en «{$esperado->etiqueta()}» y la sesión está en «{$this->estado->etiqueta()}».");
        }
    }

    public function esTarotista(User $user): bool
    {
        return $this->tarotista_id === $user->id;
    }

    public function esConsultante(User $user): bool
    {
        return $this->consultante_id !== null && $this->consultante_id === $user->id;
    }

    /** ¿Puede entrar a la sala? Lo usa routes/channels.php para el canal de presencia. */
    public function participa(User $user): bool
    {
        return $this->esTarotista($user) || $this->esConsultante($user);
    }
}
