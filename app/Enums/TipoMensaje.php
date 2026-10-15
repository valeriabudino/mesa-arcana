<?php

namespace App\Enums;

enum TipoMensaje: string
{
    case Texto = 'texto';
    case Sistema = 'sistema'; // bitácora: "María eligió su segunda carta"
    case PedidoServicio = 'pedido_servicio';
    case PedidoAclaratoria = 'pedido_aclaratoria';
    case Carta = 'carta'; // una carta revelada, con su significado (la lectura vive en el chat)
    case CartaAstral = 'carta_astral'; // la tríada calculada, con su interpretación
}
