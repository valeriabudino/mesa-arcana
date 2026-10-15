<?php

namespace App\Enums;

/** A qué está anclado un mensaje del chat (para filtrar el hilo de una carta o un planeta). */
enum TipoAncla: string
{
    case Carta = 'carta';
    case Planeta = 'planeta';
}
