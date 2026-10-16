<?php

namespace App\Interpretacion;

/**
 * Escribe la interpretación de una lectura (plan v3 §13.6). Recibe SOLO la lectura
 * (ver InterpretacionService::datosDe): cartas, posiciones, orientación, tema y pregunta.
 */
interface Interpretador
{
    /**
     * @param  array{tipo: string, tema: string|null, pregunta: string|null, cartas: list<array{posicion: string, nombre: string, invertida: bool, significado: string, aclara: string|null}>, respuesta: string|null}  $lectura
     * @param  callable(string): void  $alAvanzar  recibe el texto escrito hasta ahora (para el borrador en vivo)
     */
    public function interpretar(array $lectura, callable $alAvanzar): string;

    /** "ia" o "base": se guarda con el borrador. */
    public function fuente(): string;
}
