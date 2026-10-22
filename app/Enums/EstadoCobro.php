<?php

namespace App\Enums;

/** Qué pasó con los créditos de una lectura. */
enum EstadoCobro: string
{
    case Reservado = 'reservado'; // se aceptó: los créditos esperan a que termine
    case Cobrado = 'cobrado'; // se cerró: pasaron a la tarotista
    case Liberado = 'liberado'; // se interrumpió a medias: volvieron a quien consulta
}
