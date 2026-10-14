<?php

namespace App\Services;

use App\Enums\EstadoSesion;
use App\Enums\TipoTirada;
use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\ConsultanteIngreso;
use App\Events\MazoMezclado;
use App\Events\SesionFinalizada;
use App\Exceptions\AccionFueraDeOrden;
use App\Models\Carta;
use App\Models\Sesion;
use App\Models\SesionCarta;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Toda la lógica de una lectura. Patrón de cada acción:
 *   1. DB::transaction + lockForUpdate sobre la fila de la sesión (el "molinete").
 *   2. Validar el estado, modificar y guardar.
 *   3. Recién después del commit, emitir el evento por Reverb.
 */
class LecturaService
{
    public function crear(User $tarotista, TipoTirada $tipo, ?string $pregunta): Sesion
    {
        $sesion = new Sesion(['tipo_tirada' => $tipo, 'pregunta' => $pregunta]);
        $sesion->tarotista_id = $tarotista->id;
        $sesion->save();

        return $sesion;
    }

    /**
     * Entrar a la mesa. La primera consultante que abre el link queda asociada (Esperando → Lista).
     */
    public function entrar(Sesion $sesion, User $user): Sesion
    {
        if ($sesion->participa($user)) {
            return $sesion;
        }

        $sesion = DB::transaction(function () use ($sesion, $user) {
            $s = $this->bloquear($sesion);

            // Otra consultante pudo haber entrado mientras esperábamos el lock.
            if ($s->consultante_id !== null) {
                throw new AuthorizationException('Esta lectura ya tiene consultante.');
            }

            $s->consultante_id = $user->id;
            $s->pasarA(EstadoSesion::Lista);
            $s->save();

            return $s;
        });

        broadcast(new ConsultanteIngreso($sesion->uuid, $user->name));

        return $sesion;
    }

    /**
     * Mezcla en el servidor (Lista → Eligiendo). El orden real queda en orden_mazo ($hidden).
     */
    public function mezclar(Sesion $sesion): Sesion
    {
        $sesion = DB::transaction(function () use ($sesion) {
            $s = $this->bloquear($sesion);

            $s->pasarA(EstadoSesion::Eligiendo);
            $s->orden_mazo = $this->mezclarIds(Carta::pluck('id')->all());
            $s->save();

            return $s;
        });

        // Semilla SOLO visual: un número aparte, sin relación con orden_mazo.
        broadcast(new MazoMezclado($sesion->uuid, random_int(1, PHP_INT_MAX)));

        return $sesion;
    }

    /**
     * La consultante toca la carta $indiceAbanico (0..77) del abanico.
     * Si es la última de la tirada, pasa a Revelando en la misma transacción.
     */
    public function elegir(Sesion $sesion, int $indiceAbanico): SesionCarta
    {
        [$sesion, $elegida] = DB::transaction(function () use ($sesion, $indiceAbanico) {
            $s = $this->bloquear($sesion);
            $s->exigirEstado(EstadoSesion::Eligiendo);

            if ($s->cartas()->where('indice_abanico', $indiceAbanico)->exists()) {
                throw new AccionFueraDeOrden('Esa carta ya fue elegida.');
            }

            $posicion = $s->cartas()->count();

            $elegida = new SesionCarta(['posicion' => $posicion, 'indice_abanico' => $indiceAbanico]);
            $elegida->sesion_id = $s->id;
            $elegida->carta_id = $s->orden_mazo[$indiceAbanico];
            $elegida->invertida = random_int(0, 1) === 1;
            $elegida->save();

            if ($posicion + 1 === $s->tipo_tirada->cantidad()) {
                $s->pasarA(EstadoSesion::Revelando);
                $s->save();
            }

            return [$s, $elegida];
        });

        broadcast(new CartaElegida($sesion->uuid, $elegida->posicion, $elegida->indice_abanico));

        return $elegida;
    }

    /**
     * La tarotista da vuelta la carta de una posición. Recién acá el evento lleva la carta.
     */
    public function revelar(Sesion $sesion, int $posicion): SesionCarta
    {
        [$sesion, $carta] = DB::transaction(function () use ($sesion, $posicion) {
            $s = $this->bloquear($sesion);
            $s->exigirEstado(EstadoSesion::Revelando);

            $carta = $s->cartas()->where('posicion', $posicion)->firstOrFail();

            if ($carta->estaRevelada()) {
                throw new AccionFueraDeOrden('Esa carta ya está revelada.');
            }

            $carta->revelada_at = now();
            $carta->save();

            return [$s, $carta];
        });

        broadcast(new CartaRevelada($sesion->uuid, $carta->load('carta'), $sesion->tipo_tirada->nombrePosicion($posicion)));

        return $carta;
    }

    /**
     * Cierra la lectura (Revelando → Finalizada). Exige que estén todas reveladas.
     */
    public function finalizar(Sesion $sesion): Sesion
    {
        $sesion = DB::transaction(function () use ($sesion) {
            $s = $this->bloquear($sesion);

            if ($s->cartas()->whereNull('revelada_at')->exists()) {
                throw new AccionFueraDeOrden('Todavía hay cartas sin revelar.');
            }

            $s->pasarA(EstadoSesion::Finalizada);
            $s->finalizada_at = now();
            $s->save();

            return $s;
        });

        broadcast(new SesionFinalizada($sesion->uuid, route('sesiones.resumen', $sesion)));

        return $sesion;
    }

    /**
     * Relee la sesión con SELECT … FOR UPDATE: cualquier otra acción sobre
     * esta lectura espera hasta que la transacción actual termine.
     */
    private function bloquear(Sesion $sesion): Sesion
    {
        return Sesion::whereKey($sesion->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Fisher-Yates con random_int (criptográficamente seguro).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function mezclarIds(array $ids): array
    {
        for ($i = count($ids) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }

        return $ids;
    }
}
