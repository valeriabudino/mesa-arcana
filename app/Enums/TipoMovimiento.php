<?php

namespace App\Enums;

/** Por qué cambió un saldo (plan v3 §13.2). */
enum TipoMovimiento: string
{
    case Regalo = 'regalo'; // los créditos de bienvenida (mientras la billetera sea ficticia)
    case Compra = 'compra'; // carga con Mercado Pago (§13.3, todavía no)
    case Reserva = 'reserva'; // disponible → reservado, al aceptar el pedido
    case Liberacion = 'liberacion'; // reservado → disponible, si la lectura no se completa
    case Cobro = 'cobro'; // sale lo reservado, al cerrar la lectura
    case Ingreso = 'ingreso'; // le entra a la tarotista lo cobrado
    case Comision = 'comision'; // la parte de la plataforma, descontada a la tarotista
    case Retiro = 'retiro'; // la tarotista retira (§13.4, todavía no)
}
