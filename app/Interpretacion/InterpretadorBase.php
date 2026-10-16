<?php

namespace App\Interpretacion;

/** Sin IA: arma la interpretación con los significados de la base. Es el respaldo y nunca falla. */
class InterpretadorBase implements Interpretador
{
    public function interpretar(array $lectura, callable $alAvanzar): string
    {
        $partes = [];
        if ($lectura['pregunta']) {
            $partes[] = "Sobre «{$lectura['pregunta']}»:";
        }
        foreach ($lectura['cartas'] as $carta) {
            $donde = $carta['aclara'] ? "Aclarando «{$carta['aclara']}»" : $carta['posicion'];
            $partes[] = "{$donde}: {$carta['nombre']}".($carta['invertida'] ? ' (invertida)' : '').". {$carta['significado']}";
        }
        if ($lectura['respuesta']) {
            $partes[] = "La respuesta de las cartas: {$lectura['respuesta']}.";
        }

        $texto = implode("\n\n", $partes);
        $alAvanzar($texto);

        return $texto;
    }

    public function fuente(): string
    {
        return 'base';
    }
}
