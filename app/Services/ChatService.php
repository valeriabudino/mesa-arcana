<?php

namespace App\Services;

use App\Enums\EstadoConsulta;
use App\Enums\EstadoPedido;
use App\Enums\TipoLectura;
use App\Enums\TipoMensaje;
use App\Events\MensajeEnviado;
use App\Exceptions\AccionFueraDeOrden;
use App\Models\Consulta;
use App\Models\Mensaje;
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

    /**
     * La consultante pide un servicio desde el chat. Con lock sobre la consulta:
     * no puede haber dos pedidos pendientes ni pedir con una lectura en curso.
     */
    public function pedir(Consulta $consulta, User $consultante, TipoLectura $servicio, ?string $pregunta): Mensaje
    {
        if (! $servicio->esDeCartas()) {
            throw new AccionFueraDeOrden('La carta astral llega pronto.');
        }

        $mensaje = DB::transaction(function () use ($consulta, $consultante, $servicio, $pregunta) {
            $c = Consulta::whereKey($consulta->id)->lockForUpdate()->firstOrFail();
            $c->exigirEstado(EstadoConsulta::Activa);

            if ($c->lecturaActiva()->exists()) {
                throw new AccionFueraDeOrden('Ya hay una lectura en curso.');
            }
            if ($c->mensajes()->where('estado_pedido', EstadoPedido::Pendiente)->exists()) {
                throw new AccionFueraDeOrden('Ya hay un pedido esperando respuesta.');
            }

            $mensaje = new Mensaje(['contenido' => $pregunta ?? '']);
            $mensaje->consulta_id = $c->id;
            $mensaje->user_id = $consultante->id;
            $mensaje->tipo = TipoMensaje::PedidoServicio;
            $mensaje->servicio = $servicio;
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
    public function mensajes(Consulta $consulta, int $despues = 0): array
    {
        return $consulta->mensajes()
            ->with('autora')
            ->where('id', '>', $despues)
            ->get()
            ->map(fn (Mensaje $m) => $m->datosPublicos())
            ->all();
    }

    private function crear(Consulta $consulta, TipoMensaje $tipo, string $contenido, ?User $autora = null): Mensaje
    {
        $mensaje = new Mensaje(['contenido' => $contenido]);
        $mensaje->consulta_id = $consulta->id;
        $mensaje->user_id = $autora?->id;
        $mensaje->tipo = $tipo;
        $mensaje->save();

        $this->emitir(new MensajeEnviado($consulta->uuid, $mensaje->setRelation('autora', $autora)));

        return $mensaje;
    }
}
