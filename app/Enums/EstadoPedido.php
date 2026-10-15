<?php

namespace App\Enums;

/** Un pedido de servicio (o de carta aclaratoria) hecho desde el chat. */
enum EstadoPedido: string
{
    case Pendiente = 'pendiente';
    case Aceptado = 'aceptado';
    case Rechazado = 'rechazado';
    case Cancelado = 'cancelado'; // lo retiró la consultante antes de que le respondieran
}
