<?php

namespace App\Interpretacion;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use RuntimeException;

/**
 * La interpretación la escribe un modelo (Groq por defecto), en streaming: cada ~300 ms
 * se avisa el texto acumulado, que le llega a la tarotista como borrador.
 * Si falla o tarda más que el timeout, tira una excepción y InterpretacionService usa la base.
 */
class InterpretadorIA implements Interpretador
{
    // Los límites del plan: tono, idioma y lo que NO se dice.
    private const SISTEMA = <<<'TXT'
        Sos una tarotista con experiencia. Escribís en español rioplatense (voseo), con calidez y
        sin solemnidad, como si hablaras con quien consulta frente a la mesa.

        Te paso una lectura de tarot: el tipo de tirada, el tema, la pregunta y cada carta con su
        posición, su orientación y su significado tradicional. Escribí una interpretación que
        hile las cartas entre sí y con la pregunta, en 3 o 4 párrafos cortos (no más de 220
        palabras). Nombrá cada carta al menos una vez. Si es una tirada de sí o no, respetá la
        respuesta que te paso y explicala.

        Límites que no se cruzan nunca:
        - Nada de diagnósticos ni consejos de salud: si el tema roza la salud, hablá de bienestar
          y sugerí consultar a un profesional.
        - Nada de consejos financieros concretos (inversiones, montos, comprar o vender).
        - Nada de predicciones de muerte, enfermedad grave, accidentes o tragedias.
        - El tarot orienta, no determina: nada de certezas absolutas sobre el futuro.
        Texto plano, sin títulos ni listas ni markdown.
        TXT;

    public function __construct(
        private string $proveedor,
        private string $modelo,
        private int $timeout,
        private int $intervaloMs = 300,
    ) {}

    public function interpretar(array $lectura, callable $alAvanzar): string
    {
        $texto = '';
        $ultimoAviso = 0.0;
        $inicio = microtime(true);

        $eventos = Prism::text()
            ->using(Provider::from($this->proveedor), $this->modelo)
            ->withSystemPrompt(self::SISTEMA)
            ->withPrompt($this->prompt($lectura))
            ->withMaxTokens(700)
            ->usingTemperature(0.8)
            ->withClientOptions(['timeout' => $this->timeout, 'connect_timeout' => 5])
            ->asStream();

        foreach ($eventos as $evento) {
            if (microtime(true) - $inicio > $this->timeout) {
                throw new RuntimeException("La IA tardó más de {$this->timeout} s.");
            }
            if (! $evento instanceof TextDeltaEvent) {
                continue;
            }
            $texto .= $evento->delta;
            if ((microtime(true) - $ultimoAviso) * 1000 >= $this->intervaloMs) {
                $alAvanzar($texto);
                $ultimoAviso = microtime(true);
            }
        }

        $texto = trim($texto);
        if ($texto === '') {
            throw new RuntimeException('La IA no devolvió texto.');
        }
        $alAvanzar($texto);

        return $texto;
    }

    public function fuente(): string
    {
        return 'ia';
    }

    /** @param  array<string, mixed>  $lectura */
    private function prompt(array $lectura): string
    {
        $lineas = ["Tirada: {$lectura['tipo']}"];
        if ($lectura['tema']) {
            $lineas[] = "Tema: {$lectura['tema']}";
        }
        if ($lectura['pregunta']) {
            $lineas[] = "Pregunta: {$lectura['pregunta']}";
        }
        $lineas[] = 'Cartas:';
        foreach ($lectura['cartas'] as $carta) {
            $donde = $carta['aclara'] ? "aclaratoria de «{$carta['aclara']}»" : $carta['posicion'];
            $lineas[] = "- {$donde}: {$carta['nombre']}".($carta['invertida'] ? ' (invertida)' : '')." — {$carta['significado']}";
        }
        if ($lectura['respuesta']) {
            $lineas[] = "Respuesta de la tirada de sí o no: {$lectura['respuesta']}";
        }

        return implode("\n", $lineas);
    }
}
