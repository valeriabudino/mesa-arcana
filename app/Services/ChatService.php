<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoPedido;
use App\Enums\TipoAncla;
use App\Enums\TipoLectura;
use App\Enums\TipoMensaje;
use App\Events\InterpretacionPublicada;
use App\Events\MensajeEnviado;
use App\Events\PedidoResuelto;
use App\Exceptions\AccionFueraDeOrden;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\LecturaCarta;
use App\Models\Mensaje;
use App\Models\Pregunta;
use App\Models\User;
use App\Services\Concerns\EmiteEventos;
use Illuminate\Support\Facades\DB;

/**
 * El chat de la sala privada. El contenido se guarda TAL CUAL (texto plano):
 * la defensa contra XSS está al mostrarlo (textContent en JS, {{ }} en Blade).
 */
class ChatService
{
    use EmiteEventos;

    /** Un mensaje de una de las dos. */
    public function enviar(Consulta $consulta, User $autora, string $contenido): Mensaje
    {
        $consulta->exigirEstado(EstadoConsulta::Activa);

        return $this->crear($consulta, TipoMensaje::Texto, $contenido, $autora);
    }

    /** Bitácora: "María eligió su segunda carta". Sin autora. */
    public function sistema(Consulta $consulta, string $texto): Mensaje
    {
        return $this->crear($consulta, TipoMensaje::Sistema, $texto);
    }

    /** Una carta revelada, como mensaje: la lectura se sigue en el chat. */
    public function carta(LecturaCarta $carta): Mensaje
    {
        // El texto es la versión corta (la usa el resumen); el chat dibuja la carta completa.
        $texto = "{$carta->nombrePosicion()}: {$carta->carta->nombre}".($carta->invertida ? ' (invertida)' : '');

        return $this->crear($carta->lectura->consulta, TipoMensaje::Carta, $texto, lectura: $carta->lectura, posicion: $carta->posicion);
    }

    /** La interpretación que publica la tarotista. Viaja como InterpretacionPublicada (plan v3 §13.7). */
    public function interpretacion(Lectura $lectura, string $texto): Mensaje
    {
        $consulta = $lectura->consulta;
        $mensaje = $this->crear($consulta, TipoMensaje::Interpretacion, $texto, $consulta->tarotista, lectura: $lectura, avisar: false);
        $this->emitir(new InterpretacionPublicada($consulta->uuid, $mensaje));

        return $mensaje;
    }

    /** La carta astral calculada, como mensaje. */
    public function cartaAstral(Lectura $lectura): Mensaje
    {
        $nombres = ['sol' => 'Sol', 'luna' => 'Luna', 'ascendente' => 'Ascendente'];
        $puntos = collect($lectura->resultado['puntos'])->map(fn (array $p, string $clave) => "{$nombres[$clave]} en {$p['signo_nombre']}");

        return $this->crear($lectura->consulta, TipoMensaje::CartaAstral, 'Carta astral: '.$puntos->join(', ', ' y ').'.', lectura: $lectura);
    }

    /**
     * La consultante pide un servicio desde el chat. Con lock sobre la consulta:
     * no puede haber dos pedidos pendientes ni pedir con una lectura a medias.
     * Con la lectura ya terminada (todas las cartas a la vista, o la carta astral
     * calculada) sí: si la tarotista acepta, esa se cierra y arranca la nueva.
     */
    public function pedir(Consulta $consulta, User $consultante, TipoLectura $servicio, ?Pregunta $pregunta): Mensaje
    {
        $mensaje = DB::transaction(function () use ($consulta, $consultante, $servicio, $pregunta) {
            $c = Consulta::whereKey($consulta->id)->lockForUpdate()->firstOrFail();
            $c->exigirEstado(EstadoConsulta::Activa);

            if ($c->lecturaActiva && ! $c->lecturaActiva->terminada()) {
                throw new AccionFueraDeOrden('Ya hay una lectura en curso.');
            }
            if ($c->mensajes()->where('estado_pedido', EstadoPedido::Pendiente)->exists()) {
                throw new AccionFueraDeOrden('Ya hay un pedido esperando respuesta.');
            }

            // El texto de la pregunta queda también en el contenido (lo usa el resumen).
            $mensaje = new Mensaje(['contenido' => $pregunta->texto ?? '']);
            $mensaje->consulta_id = $c->id;
            $mensaje->user_id = $consultante->id;
            $mensaje->pregunta_id = $pregunta?->id;
            $mensaje->tipo = TipoMensaje::PedidoServicio;
            $mensaje->servicio = $servicio;
            $mensaje->estado_pedido = EstadoPedido::Pendiente;
            $mensaje->save();

            return $mensaje;
        });

        $this->emitir(new MensajeEnviado($consulta->uuid, $mensaje->load('autora', 'pregunta.tema')));

        return $mensaje;
    }

    /**
     * La consultante pide una carta aclaratoria para la carta de una posición.
     * Queda anclado a esa carta (para el hilo del chat y para saber qué aclarar).
     */
    public function pedirAclaratoria(Lectura $lectura, User $consultante, int $posicion): Mensaje
    {
        $consulta = $lectura->consulta;

        $mensaje = DB::transaction(function () use ($consulta, $lectura, $consultante, $posicion) {
            $c = Consulta::whereKey($consulta->id)->lockForUpdate()->firstOrFail();
            $c->exigirEstado(EstadoConsulta::Activa);

            if ($c->lecturaActiva?->id !== $lectura->id) {
                throw new AccionFueraDeOrden('Esa lectura ya no está en curso.');
            }
            if ($motivo = $lectura->fresh()->motivoParaNoAclarar($posicion)) {
                throw new AccionFueraDeOrden($motivo);
            }
            if ($c->mensajes()->where('estado_pedido', EstadoPedido::Pendiente)->exists()) {
                throw new AccionFueraDeOrden('Ya hay un pedido esperando respuesta.');
            }

            $mensaje = new Mensaje(['contenido' => '']); // sin texto libre: aclara la carta de esa posición
            $mensaje->consulta_id = $c->id;
            $mensaje->user_id = $consultante->id;
            $mensaje->tipo = TipoMensaje::PedidoAclaratoria;
            $mensaje->ancla_tipo = TipoAncla::Carta;
            $mensaje->ancla_valor = (string) $posicion;
            $mensaje->estado_pedido = EstadoPedido::Pendiente;
            $mensaje->save();

            return $mensaje;
        });

        $this->emitir(new MensajeEnviado($consulta->uuid, $mensaje->load('autora')));

        return $mensaje;
    }

    /**
     * Mensajes posteriores a $despues (para cargar el chat y al reconectar).
     *
     * @return list<array<string, mixed>>
     */
    /**
     * La consultante retira su pedido (de servicio o de aclaratoria) mientras sigue
     * pendiente: así puede elegir otra cosa. Con lock: si la tarotista justo lo
     * respondió, gana la que llegó primero.
     */
    public function cancelarPedido(Mensaje $pedido, User $consultante): void
    {
        $consulta = DB::transaction(function () use ($pedido, $consultante) {
            $c = Consulta::whereKey($pedido->consulta_id)->lockForUpdate()->firstOrFail();
            $c->exigirEstado(EstadoConsulta::Activa);

            $p = Mensaje::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            if ($p->user_id !== $consultante->id) {
                throw new AccionFueraDeOrden('Ese pedido no es tuyo.');
            }
            if ($p->estado_pedido !== EstadoPedido::Pendiente) {
                throw new AccionFueraDeOrden('Ese pedido ya fue respondido.');
            }
            $p->estado_pedido = EstadoPedido::Cancelado;
            $p->save();

            return $c;
        });

        $this->emitir(new PedidoResuelto($consulta->uuid, $pedido->id, EstadoPedido::Cancelado->value));
        $this->sistema($consulta, "{$consultante->name} canceló su pedido.");
    }

    public function mensajes(Consulta $consulta, int $despues = 0): array
    {
        return $consulta->mensajes()
            ->with(['autora', 'lectura.cartas.carta', 'pregunta.tema'])
            ->where('id', '>', $despues)
            ->get()
            ->map(fn (Mensaje $m) => $m->datosPublicos())
            ->all();
    }

    private function crear(
        Consulta $consulta,
        TipoMensaje $tipo,
        string $contenido,
        ?User $autora = null,
        ?Lectura $lectura = null,
        ?int $posicion = null,
        bool $avisar = true,
    ): Mensaje {
        $mensaje = new Mensaje(['contenido' => $contenido]);
        $mensaje->consulta_id = $consulta->id;
        $mensaje->user_id = $autora?->id;
        $mensaje->lectura_id = $lectura?->id;
        $mensaje->tipo = $tipo;
        if ($posicion !== null) {
            $mensaje->ancla_tipo = TipoAncla::Carta;
            $mensaje->ancla_valor = (string) $posicion;
        }
        $mensaje->save();
        if ($lectura) {
            $mensaje->setRelation('lectura', $lectura->load('cartas.carta'));
        }

        $mensaje->setRelation('autora', $autora);
        if ($avisar) {
            $this->emitir(new MensajeEnviado($consulta->uuid, $mensaje));
        }

        return $mensaje;
    }
}
