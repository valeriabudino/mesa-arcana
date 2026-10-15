<?php

namespace App\Enums;

enum TipoMensaje: string
{
    case Texto = 'texto';
    case Sistema = 'sistema'; // bitácora: "María eligió su segunda carta"
    case PedidoServicio = 'pedido_servicio';
    case PedidoAclaratoria = 'pedido_aclaratoria';
}
