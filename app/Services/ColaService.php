<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoTurno;
use App\Enums\Rol;
use App\Events\ColaActualizada;
use App\Events\TurnoActualizado;
use App\Events\TurnoCancelado;
use App\Events\TurnoCreado;
use App\Events\TurnoTomado;
use App\Events\TuTarotistaTeEspera;
use App\Exceptions\AccionFueraDeOrden;
use App\Models\Consulta;
use App\Models\Turno;
use App\Models\User;
use App\Services\Concerns\EmiteEventos;
use Illuminate\Support\Facades\DB;

/**
 * La sala de espera. La fila real vive en la tabla turnos (ordenada por llegada);
 * Reverb solo AVISA que cambió. Mismo patrón que las lecturas:
 * transacción + lockForUpdate, y los eventos recién después del commit.
 */
class ColaService
{
    use EmiteEventos;

    public function __construct(private BilleteraService $billetera) {}

    /** Una visitante escribe su nombre: se crea su usuaria invitada (sin email ni contraseña). */
    public function crearInvitada(string $nombre): User
    {
        $invitada = new User(['name' => $nombre]);
        $invitada->rol = Rol::Consultante;
        $invitada->save();
        $this->billetera->regalar($invitada);

        return $invitada;
    }

    /**
     * Entrar a la fila. Si ya está esperando, devuelve su turno (no se duplica).
     */
    public function pedirTurno(User $consultante, ?string $motivo): Turno
    {
        $existente = $this->turnoVigente($consultante);
        if ($existente !== null) {
            return $existente;
        }

        $turno = new Turno(['motivo' => $motivo]);
        $turno->consultante_id = $consultante->id;
        $turno->save();

        $this->emitir(new TurnoCreado($turno->load('consultante')));
        $this->emitir(new ColaActualizada);

        return $turno;
    }

    /** La consultante cambia el motivo mientras espera. */
    public function cambiarMotivo(Turno $turno, ?string $motivo): Turno
    {
        $turno = DB::transaction(function () use ($turno, $motivo) {
            $t = Turno::whereKey($turno->id)->lockForUpdate()->firstOrFail();
            $t->exigirEstado(EstadoTurno::Esperando); // si ya la atendieron, no tiene sentido
            $t->motivo = $motivo;
            $t->save();

            return $t;
        });

        $this->emitir(new TurnoActualizado($turno->load('consultante')));

        return $turno;
    }

    /** La consultante sale de la fila (Esperando → Cancelado). */
    public function cancelar(Turno $turno): Turno
    {
        $turno = DB::transaction(function () use ($turno) {
            $t = Turno::whereKey($turno->id)->lockForUpdate()->firstOrFail();
            $t->pasarA(EstadoTurno::Cancelado);
            $t->save();

            return $t;
        });

        $this->emitir(new TurnoCancelado($turno->uuid));
        $this->emitir(new ColaActualizada);

        return $turno;
    }

    /**
     * Una tarotista toma un turno y nace la consulta.
     *
     * Dos locks, siempre en el mismo orden (primero la tarotista, después el turno)
     * para que dos transacciones nunca se esperen mutuamente:
     *   - el de la tarotista evita que con un doble clic atienda dos turnos a la vez;
     *   - el del turno es el "molinete": si dos tarotistas tocan Atender a la vez,
     *     la segunda espera, relee y ve que ya está Tomado → 409 "Lo tomó …".
     */
    public function tomar(Turno $turno, User $tarotista): Consulta
    {
        [$turno, $consulta] = DB::transaction(function () use ($turno, $tarotista) {
            $yo = User::whereKey($tarotista->id)->lockForUpdate()->firstOrFail();
            $t = Turno::whereKey($turno->id)->lockForUpdate()->firstOrFail();

            if ($t->estado === EstadoTurno::Tomado) {
                throw new AccionFueraDeOrden("Lo tomó {$t->tarotista->name}.");
            }
            if (! $yo->disponible) {
                throw new AccionFueraDeOrden('Activá «Disponible» para atender.');
            }
            if (Consulta::where('tarotista_id', $yo->id)->where('estado', EstadoConsulta::Activa)->exists()) {
                throw new AccionFueraDeOrden('Ya tenés una consulta en curso.');
            }

            $t->pasarA(EstadoTurno::Tomado); // si estaba Cancelado, también da 409
            $t->tarotista_id = $yo->id;
            $t->tomado_at = now();
            $t->save();

            $consulta = new Consulta;
            $consulta->turno_id = $t->id;
            $consulta->tarotista_id = $yo->id;
            $consulta->consultante_id = $t->consultante_id;
            $consulta->save();

            return [$t, $consulta];
        });

        $this->emitir(new TurnoTomado($turno->uuid, $tarotista->name));
        $this->emitir(new ColaActualizada);
        $this->emitir(new TuTarotistaTeEspera($turno->consultante_id, $tarotista->name, route('consultas.show', $consulta)));

        return $consulta;
    }

    public function cambiarDisponible(User $tarotista, bool $disponible): void
    {
        $tarotista->disponible = $disponible;
        $tarotista->save();
    }

    /** El turno que la consultante tiene esperando, si tiene. */
    public function turnoVigente(User $consultante): ?Turno
    {
        return Turno::where('consultante_id', $consultante->id)
            ->where('estado', EstadoTurno::Esperando)
            ->latest('id')
            ->first();
    }

    /** Si ya la atendieron y la consulta sigue activa, a dónde tiene que ir. */
    public function consultaActiva(User $user): ?Consulta
    {
        return Consulta::where('estado', EstadoConsulta::Activa)
            ->where(fn ($q) => $q->where('consultante_id', $user->id)->orWhere('tarotista_id', $user->id))
            ->latest('id')
            ->first();
    }

    /**
     * Foto para la sala de espera (al entrar y al reconectar): la fila anónima
     * y el estado del turno propio.
     *
     * @return array{turno: array{uuid: string, estado: string}|null, cola: list<string>, url_consulta: string|null}
     */
    public function estadoEspera(User $consultante): array
    {
        $turno = Turno::where('consultante_id', $consultante->id)->latest('id')->first();
        $consulta = $this->consultaActiva($consultante);

        return [
            'turno' => $turno ? ['uuid' => $turno->uuid, 'estado' => $turno->estado->value] : null,
            'cola' => Turno::uuidsEnEspera(),
            'url_consulta' => $consulta ? route('consultas.show', $consulta) : null,
        ];
    }

    /**
     * La fila para el panel de atención, con los mismos datos que TurnoCreado.
     *
     * @return list<array<string, mixed>>
     */
    public function colaParaTarotistas(): array
    {
        return Turno::with('consultante')
            ->where('estado', EstadoTurno::Esperando)
            ->orderBy('created_at')->orderBy('id')
            ->get()
            ->map(fn (Turno $t) => $t->datosParaTarotistas())
            ->all();
    }
}
