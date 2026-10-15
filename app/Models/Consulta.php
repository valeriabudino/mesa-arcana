<?php

namespace App\Models;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoLectura;
use App\Models\Concerns\TieneMaquinaDeEstados;
use App\Models\Concerns\TieneUuidPublico;
use Database\Factories\ConsultaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * La sala privada entre una tarotista y una consultante. Nace al tomar un turno.
 *
 * @property int $id
 * @property string $uuid
 * @property int $turno_id
 * @property int $tarotista_id
 * @property int $consultante_id
 * @property EstadoConsulta $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $finalizada_at
 */
class Consulta extends Model // sin #[Fillable]: todo lo asigna ColaService / ConsultaService
{
    /** @use HasFactory<ConsultaFactory> */
    use HasFactory, TieneMaquinaDeEstados, TieneUuidPublico;

    protected $attributes = [
        'estado' => 'activa',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoConsulta::class,
            'finalizada_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Turno, $this> */
    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
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

    /** @return HasMany<Lectura, $this> */
    public function lecturas(): HasMany
    {
        return $this->hasMany(Lectura::class)->orderBy('id');
    }

    /**
     * La lectura en curso (hay como mucho una): la última que no está cerrada.
     *
     * @return HasOne<Lectura, $this>
     */
    public function lecturaActiva(): HasOne
    {
        return $this->hasOne(Lectura::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('estado', '!=', EstadoLectura::Cerrada->value),
        );
    }

    /** @return HasMany<Mensaje, $this> */
    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class)->orderBy('id');
    }

    public function esTarotista(User $user): bool
    {
        return $this->tarotista_id === $user->id;
    }

    public function esConsultante(User $user): bool
    {
        return $this->consultante_id === $user->id;
    }

    /** ¿Puede entrar a la sala? Lo usa routes/channels.php para el canal de presencia. */
    public function participa(User $user): bool
    {
        return $this->esTarotista($user) || $this->esConsultante($user);
    }
}
