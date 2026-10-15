<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoLectura;
use App\Enums\EstadoPedido;
use App\Enums\EstadoTurno;
use App\Enums\TipoMensaje;
use App\Events\AclaratoriaAceptada;
use App\Events\ConsultaFinalizada;
use App\Events\EstadoCambiado;
use App\Events\LecturaIniciada;
use App\Events\PedidoResuelto;
use App\Exceptions\AccionFueraDeOrden;
use App\Models\Ciudad;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\Mensaje;
use App\Models\Turno;
use App\Services\Concerns\EmiteEventos;
use Illuminate\Support\Facades\DB;

/**
 * La sala privada: responder pedidos (y así iniciar lecturas), cerrarlas y
 * finalizar la consulta. El lock va sobre la fila de la consulta: es lo que
 * garantiza "una lectura activa a la vez".
 */
class ConsultaService
{
    use EmiteEventos;

    /** Si la consultante no entra en este tiempo, la tarotista puede marcarla ausente. */
    public const MINUTOS_PARA_AUSENTE = 2;

    public function __construct(
        private LecturaCartasService $cartas,
        private ChatService $chat,
    ) {}

    /**
     * La tarotista acepta un pedido del chat: un servicio (nace una lectura)
     * o una carta aclaratoria (la lectura abierta vuelve a Eligiendo).
     */
    public function aceptarPedido(Mensaje $pedido): Lectura
    {
        if ($pedido->tipo === TipoMensaje::PedidoAclaratoria) {
            return $this->aceptarAclaratoria($pedido);
        }

        [$consulta, $lectura, $anterior] = DB::transaction(function () use ($pedido) {
            [$c, $p] = $this->bloquearConPedido($pedido);

            // Si la consultante la pidió con la lectura anterior ya terminada, esa se cierra sola.
            $anterior = $c->lecturaActiva;
            if ($anterior && ! $anterior->terminada()) {
                throw new AccionFueraDeOrden('Ya hay una lectura en curso: cerrala antes de empezar otra.');
            }
            if ($anterior) {
                $anterior->pasarA(EstadoLectura::Cerrada);
                $anterior->cerrada_at = now();
                $anterior->save();
            }

            $p->estado_pedido = EstadoPedido::Aceptado;
            $p->save();

            $lectura = new Lectura(['tipo' => $p->servicio, 'pregunta' => $p->contenido !== '' ? $p->contenido : null]);
            $lectura->consulta_id = $c->id;
            $lectura->save();

            return [$c, $lectura, $anterior];
        });

        $this->emitir(new PedidoResuelto($consulta->uuid, $pedido->id, EstadoPedido::Aceptado->value));
        if ($anterior) {
            $this->emitir(new EstadoCambiado($consulta->uuid, $anterior->id, EstadoLectura::Cerrada->value));
            $this->chat->sistema($consulta, "Se cerró la lectura: {$anterior->tipo->nombre()}.");
        }
        $this->emitir(new LecturaIniciada($consulta->uuid, $lectura));
        $this->chat->sistema($consulta, "{$consulta->tarotista->name} aceptó: {$lectura->tipo->nombre()}.");

        return $lectura;
    }

    private function aceptarAclaratoria(Mensaje $pedido): Lectura
    {
        $aclara = (int) $pedido->ancla_valor;

        [$consulta, $lectura] = DB::transaction(function () use ($pedido, $aclara) {
            [$c, $p] = $this->bloquearConPedido($pedido);
            $lectura = $c->lecturaActiva ?? throw new AccionFueraDeOrden('No hay una lectura en curso.');

            $lectura = $this->cartas->aceptarAclaratoria($lectura, $aclara);
            $p->estado_pedido = EstadoPedido::Aceptado;
            $p->save();

            return [$c, $lectura];
        });

        $this->emitir(new PedidoResuelto($consulta->uuid, $pedido->id, EstadoPedido::Aceptado->value));
        $this->emitir(new AclaratoriaAceptada($consulta->uuid, $lectura->id, $aclara));
        $this->chat->sistema($consulta, "{$consulta->tarotista->name} aceptó aclarar «{$lectura->tipo->nombrePosicion($aclara)}».");

        return $lectura;
    }

    public function rechazarPedido(Mensaje $pedido): void
    {
        $consulta = DB::transaction(function () use ($pedido) {
            [$c, $p] = $this->bloquearConPedido($pedido);
            $p->estado_pedido = EstadoPedido::Rechazado;
            $p->save();

            return $c;
        });

        $this->emitir(new PedidoResuelto($consulta->uuid, $pedido->id, EstadoPedido::Rechazado->value));
        $this->chat->sistema($consulta, "{$consulta->tarotista->name} no aceptó el pedido.");
    }

    /** Cerrar la lectura terminada (Abierta o Calculada → Cerrada): se puede pedir otra. */
    public function cerrarLectura(Lectura $lectura): Lectura
    {
        $lectura = DB::transaction(function () use ($lectura) {
            $this->bloquear($lectura->consulta)->exigirEstado(EstadoConsulta::Activa);
            $l = Lectura::whereKey($lectura->id)->lockForUpdate()->firstOrFail();
            $l->exigirEstado(EstadoLectura::Abierta, EstadoLectura::Calculada);
            $l->pasarA(EstadoLectura::Cerrada);
            $l->cerrada_at = now();
            $l->save();

            return $l;
        });

        $this->emitir(new EstadoCambiado($lectura->consulta->uuid, $lectura->id, EstadoLectura::Cerrada->value));
        $this->chat->sistema($lectura->consulta, "Se cerró la lectura: {$lectura->tipo->nombre()}.");

        return $lectura;
    }

    /**
     * La tarotista termina la consulta. Siempre se puede: si había una lectura a medias,
     * se interrumpe (en el resumen solo aparecen las cartas reveladas).
     */
    public function finalizar(Consulta $consulta): Consulta
    {
        $consulta = DB::transaction(function () use ($consulta) {
            $c = $this->bloquear($consulta);
            $c->pasarA(EstadoConsulta::Finalizada);
            $c->finalizada_at = now();
            $c->save();
            $this->interrumpirLectura($c);

            return $c;
        });

        $this->chat->sistema($consulta, 'La consulta terminó.');
        $this->emitir(new ConsultaFinalizada($consulta->uuid, route('consultas.resumen', $consulta)));

        return $consulta;
    }

    /**
     * La consultante nunca entró: turno Tomado → Ausente y la consulta termina,
     * así la tarotista puede atender a la siguiente.
     */
    public function marcarAusente(Consulta $consulta): Consulta
    {
        $consulta = DB::transaction(function () use ($consulta) {
            $c = $this->bloquear($consulta);
            $c->exigirEstado(EstadoConsulta::Activa);

            $turno = Turno::whereKey($c->turno_id)->lockForUpdate()->firstOrFail();
            if ($turno->tomado_at->gt(now()->subMinutes(self::MINUTOS_PARA_AUSENTE))) {
                throw new AccionFueraDeOrden('Esperá '.self::MINUTOS_PARA_AUSENTE.' minutos antes de marcar la ausencia.');
            }
            if ($c->lecturas()->exists() || $c->mensajes()->where('user_id', $c->consultante_id)->exists()) {
                throw new AccionFueraDeOrden("{$c->consultante->name} ya participó de la consulta.");
            }

            $turno->pasarA(EstadoTurno::Ausente);
            $turno->save();
            $c->pasarA(EstadoConsulta::Finalizada);
            $c->finalizada_at = now();
            $c->save();

            return $c;
        });

        $this->chat->sistema($consulta, "{$consulta->consultante->name} no llegó.");
        $this->emitir(new ConsultaFinalizada($consulta->uuid, route('consultas.resumen', $consulta)));

        return $consulta;
    }

    /**
     * Foto de la consulta para dibujar al entrar y redibujar al reconectar.
     * (Los mensajes van aparte: GET /mensajes?despues=…)
     *
     * @return array{estado: string, lectura: array<string, mixed>|null, url_resumen: string|null}
     */
    public function estado(Consulta $consulta): array
    {
        $lectura = $consulta->lecturaActiva;
        $finalizada = $consulta->estado === EstadoConsulta::Finalizada;

        return [
            'estado' => $consulta->estado->value,
            'lectura' => match (true) {
                $lectura === null => null,
                $lectura->tipo->esDeCartas() => $this->cartas->foto($lectura->setRelation('consulta', $consulta)),
                default => $this->fotoAstral($lectura),
            },
            'url_resumen' => $finalizada ? route('consultas.resumen', $consulta) : null,
        ];
    }

    /**
     * Foto de una carta astral: el resultado (igual que en CartaAstralCalculada) y, para
     * mostrarlos en la sala, los datos de nacimiento. /estado solo lo ven las dos participantes.
     *
     * @return array<string, mixed>
     */
    private function fotoAstral(Lectura $lectura): array
    {
        $datos = $lectura->datos_nacimiento;
        $ciudad = $datos ? Ciudad::find($datos['ciudad_id']) : null;

        return $lectura->datosPublicos() + [
            'resultado' => $lectura->resultado,
            'nacimiento' => $datos ? [
                'fecha' => $datos['fecha'],
                'hora' => $datos['hora'],
                'lugar' => $ciudad ? "{$ciudad->nombre}, {$ciudad->provincia}" : null,
            ] : null,
        ];
    }

    private function interrumpirLectura(Consulta $consulta): void
    {
        $lectura = $consulta->lecturaActiva()->lockForUpdate()->first();
        if ($lectura === null) {
            return;
        }

        $lectura->pasarA(EstadoLectura::Cerrada);
        $lectura->cerrada_at = now();
        $lectura->save();
    }

    private function bloquear(Consulta $consulta): Consulta
    {
        return Consulta::whereKey($consulta->id)->lockForUpdate()->firstOrFail();
    }

    /** @return array{0: Consulta, 1: Mensaje} */
    private function bloquearConPedido(Mensaje $pedido): array
    {
        $c = $this->bloquear($pedido->consulta);
        $c->exigirEstado(EstadoConsulta::Activa);

        $p = Mensaje::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
        if ($p->estado_pedido !== EstadoPedido::Pendiente) {
            throw new AccionFueraDeOrden('Ese pedido ya fue respondido.');
        }

        return [$c, $p];
    }
}
