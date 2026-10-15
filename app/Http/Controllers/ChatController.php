<?php

namespace App\Http\Controllers;

use App\Enums\TipoLectura;
use App\Models\Consulta;
use App\Models\Mensaje;
use App\Services\ChatService;
use App\Services\ConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** El chat de la sala: mensajes y pedidos de servicio. Responde 204: lo nuevo llega por Reverb. */
class ChatController extends Controller
{
    public function __construct(
        private ChatService $chat,
        private ConsultaService $consultas,
    ) {}

    /** Los mensajes posteriores a ?despues= (0 = todos): al entrar y al reconectar. */
    public function index(Request $request, Consulta $consulta): JsonResponse
    {
        $despues = (int) ($request->validate(['despues' => ['nullable', 'integer', 'min:0']])['despues'] ?? 0);

        return response()->json($this->chat->mensajes($consulta, $despues));
    }

    public function store(Request $request, Consulta $consulta): Response
    {
        // Texto plano: se valida el largo y se guarda tal cual. Escapar es tarea de quien lo muestra.
        $datos = $request->validate(['contenido' => ['required', 'string', 'max:1000']]);

        $this->chat->enviar($consulta, $request->user(), $datos['contenido']);

        return response()->noContent();
    }

    public function pedir(Request $request, Consulta $consulta): Response
    {
        $datos = $request->validate([
            'servicio' => ['required', Rule::enum(TipoLectura::class)],
            'pregunta' => ['nullable', 'string', 'max:500'],
        ]);

        $this->chat->pedir($consulta, $request->user(), TipoLectura::from($datos['servicio']), $datos['pregunta'] ?? null);

        return response()->noContent();
    }

    public function aceptar(Consulta $consulta, Mensaje $mensaje): Response
    {
        $this->consultas->aceptarPedido($mensaje);

        return response()->noContent();
    }

    public function rechazar(Consulta $consulta, Mensaje $mensaje): Response
    {
        $this->consultas->rechazarPedido($mensaje);

        return response()->noContent();
    }
}
