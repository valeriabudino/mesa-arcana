<?php

namespace App\Enums;

use App\Enums\Concerns\MaquinaDeEstados;

/**
 * Máquina de estados de una lectura dentro de una consulta.
 *
 * Tiradas de cartas:
 *   Lista ─(mezclar)─▶ Eligiendo ─(eligió las pendientes)─▶ Revelando ─(reveló todas)─▶ Abierta
 *   Abierta ─(acepta aclaratoria)─▶ Eligiendo (1 pendiente)
 *   Abierta ─(tarotista cierra)─▶ Cerrada
 *
 * Carta astral:
 *   EsperandoDatos ─(consultante envía datos)─▶ Calculada ─(tarotista cierra)─▶ Cerrada
 *
 * Interrupción: cualquier estado no final ─(la tarotista finaliza la consulta)─▶ Cerrada.
 * Así nunca queda trabada una consulta si la consultante se va a mitad de una tirada.
 * El botón "Cerrar lectura" igual exige Abierta o Calculada (lo controla ConsultaService);
 * y en el resumen solo aparecen las cartas reveladas.
 */
enum EstadoLectura: string
{
    use MaquinaDeEstados;

    case Lista = 'lista';
    case Eligiendo = 'eligiendo';
    case Revelando = 'revelando';
    case Abierta = 'abierta';
    case EsperandoDatos = 'esperando_datos';
    case Calculada = 'calculada';
    case Cerrada = 'cerrada';

    public function siguientes(): array
    {
        return match ($this) {
            self::Lista => [self::Eligiendo, self::Cerrada],
            self::Eligiendo => [self::Revelando, self::Cerrada],
            self::Revelando => [self::Abierta, self::Cerrada],
            self::Abierta => [self::Eligiendo, self::Cerrada],
            self::EsperandoDatos => [self::Calculada, self::Cerrada],
            self::Calculada => [self::Cerrada],
            self::Cerrada => [],
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Lista => 'Lista para mezclar',
            self::Eligiendo => 'Eligiendo cartas',
            self::Revelando => 'Revelando',
            self::Abierta => 'Lectura abierta',
            self::EsperandoDatos => 'Esperando los datos de nacimiento',
            self::Calculada => 'Carta calculada',
            self::Cerrada => 'Cerrada',
        };
    }
}
