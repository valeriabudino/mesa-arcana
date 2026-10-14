<?php

namespace App\Events;

use App\Models\SesionCarta;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** La tarotista dio vuelta una carta. Recién acá viaja qué carta es. */
class CartaRevelada implements ShouldBroadcastNow
{
    public function __construct(
        private string $uuid,
        private SesionCarta $sesionCarta,
        private string $nombrePosicion,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('sesion.'.$this->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'CartaRevelada';
    }

    public function broadcastWith(): array
    {
        $carta = $this->sesionCarta->carta;
        $invertida = $this->sesionCarta->invertida;

        return [
            'posicion' => $this->sesionCarta->posicion,
            'nombre_posicion' => $this->nombrePosicion,
            'invertida' => $invertida,
            'carta' => [
                'nombre' => $carta->nombre,
                'imagen' => $carta->urlImagen(),
                'significado' => $invertida ? $carta->significado_invertido : $carta->significado_derecho,
                'palabras_clave' => $carta->palabras_clave,
            ],
        ];
    }
}
