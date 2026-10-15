<?php

namespace App\Services;

use App\Models\Consulta;

/**
 * La sala privada. Por ahora arma la "foto" de GET /c/{uuid}/estado;
 * en la Fase 3 suma iniciar lecturas, responder pedidos y finalizar.
 */
class ConsultaService
{
    public function __construct(private LecturaCartasService $cartas) {}

    /**
     * Foto de la consulta para dibujar al entrar y redibujar al reconectar.
     *
     * @return array{estado: string, lectura: array<string, mixed>|null}
     */
    public function estado(Consulta $consulta): array
    {
        $lectura = $consulta->lecturaActiva;

        return [
            'estado' => $consulta->estado->value,
            'lectura' => $lectura?->tipo->esDeCartas() ? $this->cartas->foto($lectura->setRelation('consulta', $consulta)) : null,
        ];
    }
}
