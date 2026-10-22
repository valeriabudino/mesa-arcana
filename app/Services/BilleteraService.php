<?php

namespace App\Services;

use App\Enums\EstadoCobro;
use App\Enums\TipoLectura;
use App\Enums\TipoMovimiento;
use App\Events\SaldoActualizado;
use App\Exceptions\AccionFueraDeOrden;
use App\Models\Lectura;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\Concerns\EmiteEventos;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * La billetera de créditos (plan v3 §13.2). Es lo ÚNICO que toca los saldos, con dos reglas:
 * - El saldo nunca cambia sin un Movimiento en la misma transacción.
 * - Las billeteras se bloquean siempre en el mismo orden (id menor primero): así dos
 *   cobros a la vez esperan su turno en lugar de trabarse entre sí (deadlock).
 *
 * Ciclo de una lectura: aceptar → reservar · cerrar → cobrar · interrumpir a medias → liberar.
 * Los avisos (SaldoActualizado) salen recién después del commit.
 */
class BilleteraService
{
    use EmiteEventos;

    public function precio(TipoLectura $servicio): int
    {
        return (int) config("creditos.precios.{$servicio->value}", 0);
    }

    /** @return array<string, int> servicio → precio (para la sala) */
    public function precios(): array
    {
        return collect(TipoLectura::cases())->mapWithKeys(fn (TipoLectura $t) => [$t->value => $this->precio($t)])->all();
    }

    /** Lo que se queda la plataforma de un cobro (entero, redondeado hacia abajo). */
    public function comision(int $precio): int
    {
        return intdiv($precio * (int) config('creditos.comision', 0), 100);
    }

    /** Si no le alcanza, el motivo (para mostrarlo); si le alcanza, null. */
    public function motivoParaNoPedir(User $consultante, TipoLectura $servicio): ?string
    {
        $precio = $this->precio($servicio);
        $saldo = $consultante->fresh()->saldo_disponible;

        return $saldo < $precio
            ? "No te alcanzan los créditos: {$servicio->nombre()} cuesta {$precio} y tenés {$saldo}."
            : null;
    }

    /** Los créditos de bienvenida de quien entra a consultar. */
    public function regalar(User $consultante): void
    {
        $monto = (int) config('creditos.regalo_inicial', 0);
        if ($monto <= 0) {
            return;
        }

        DB::transaction(function () use ($consultante, $monto) {
            $this->mover($this->bloquear($consultante->id)[$consultante->id], TipoMovimiento::Regalo, $monto);
        });
    }

    /**
     * Al aceptar el pedido: el precio pasa de lo disponible a lo reservado y queda anotado
     * en la lectura. Se llama dentro de la transacción que crea la lectura.
     */
    public function reservar(Lectura $lectura, User $consultante): int
    {
        $precio = $this->precio($lectura->tipo);

        DB::transaction(function () use ($lectura, $consultante, $precio) {
            $billetera = $this->bloquear($consultante->id)[$consultante->id];
            if ($billetera->saldo_disponible < $precio) {
                throw new AccionFueraDeOrden("A {$consultante->name} no le alcanzan los créditos ({$billetera->saldo_disponible} de {$precio}): rechazá el pedido.");
            }
            $this->mover($billetera, TipoMovimiento::Reserva, -$precio, $precio, $lectura);

            $lectura->precio = $precio;
            $lectura->estado_cobro = EstadoCobro::Reservado;
            $lectura->save();
        });

        return $precio;
    }

    /**
     * Al cerrar la lectura: lo reservado sale de quien consulta y le entra a la tarotista,
     * menos la comisión. Devuelve lo cobrado, o null si no había nada reservado (ya cobrada,
     * o una lectura de antes de la billetera): llamarlo dos veces no cobra dos veces.
     */
    public function cobrar(Lectura $lectura): ?int
    {
        return $this->resolver($lectura, function (Lectura $l, Collection $billeteras) {
            $consulta = $l->consulta;
            $precio = $l->precio;
            $comision = $this->comision($precio);

            $this->mover($billeteras[$consulta->consultante_id], TipoMovimiento::Cobro, 0, -$precio, $l);
            $tarotista = $billeteras[$consulta->tarotista_id];
            $this->mover($tarotista, TipoMovimiento::Ingreso, $precio, 0, $l);
            if ($comision > 0) {
                $this->mover($tarotista, TipoMovimiento::Comision, -$comision, 0, $l);
            }

            return EstadoCobro::Cobrado;
        });
    }

    /** La lectura no se completó: lo reservado vuelve a quien consulta. Devuelve lo liberado, o null. */
    public function liberar(Lectura $lectura): ?int
    {
        return $this->resolver($lectura, function (Lectura $l, Collection $billeteras) {
            $this->mover($billeteras[$l->consulta->consultante_id], TipoMovimiento::Liberacion, $l->precio, -$l->precio, $l);

            return EstadoCobro::Liberado;
        });
    }

    /** @return array{disponible: int, reservado: int} */
    public function saldo(User $user): array
    {
        $user = $user->fresh();

        return ['disponible' => $user->saldo_disponible, 'reservado' => $user->saldo_reservado];
    }

    /**
     * Cobrar o liberar: bloquea la lectura y las dos billeteras, y solo actúa si los
     * créditos seguían reservados.
     *
     * @param  callable(Lectura, Collection<int, User>): EstadoCobro  $accion
     */
    private function resolver(Lectura $lectura, callable $accion): ?int
    {
        return DB::transaction(function () use ($lectura, $accion) {
            $l = Lectura::whereKey($lectura->id)->lockForUpdate()->firstOrFail();
            if ($l->estado_cobro !== EstadoCobro::Reservado) {
                return null;
            }
            $consulta = $l->consulta;
            $billeteras = $this->bloquear($consulta->consultante_id, $consulta->tarotista_id);

            $l->estado_cobro = $accion($l, $billeteras);
            $l->save();
            $lectura->estado_cobro = $l->estado_cobro;

            return $l->precio;
        });
    }

    /**
     * Bloquea las billeteras, siempre de menor a mayor id.
     *
     * @return Collection<int, User> por id
     */
    private function bloquear(int ...$ids): Collection
    {
        return User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /** Cambia el saldo (ya bloqueado) y deja su movimiento. Ningún saldo queda en negativo. */
    private function mover(User $billetera, TipoMovimiento $tipo, int $monto, int $montoReservado = 0, ?Model $referencia = null): void
    {
        $disponible = $billetera->saldo_disponible + $monto;
        $reservado = $billetera->saldo_reservado + $montoReservado;
        if ($disponible < 0 || $reservado < 0) {
            throw new AccionFueraDeOrden('No alcanzan los créditos.');
        }

        $billetera->saldo_disponible = $disponible;
        $billetera->saldo_reservado = $reservado;
        $billetera->save();

        $movimiento = new Movimiento;
        $movimiento->user_id = $billetera->id;
        $movimiento->tipo = $tipo;
        $movimiento->monto = $monto;
        $movimiento->monto_reservado = $montoReservado;
        $movimiento->saldo_disponible = $disponible;
        $movimiento->saldo_reservado = $reservado;
        $movimiento->referencia()->associate($referencia);
        $movimiento->save();

        DB::afterCommit(fn () => $this->emitir(new SaldoActualizado($billetera->id, $disponible, $reservado)));
    }
}
