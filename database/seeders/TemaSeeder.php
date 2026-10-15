<?php

namespace Database\Seeders;

use App\Models\Tema;
use Illuminate\Database\Seeder;

/** Temas y preguntas predeterminadas del plan v3 (§13.1). Se puede correr de nuevo: no duplica. */
class TemaSeeder extends Seeder
{
    private const TIRADAS = ['tres_cartas', 'cruz_celta'];

    private const SI_NO = ['si_no'];

    public function run(): void
    {
        $temas = [
            ['amor', 'Amor', '💗', [
                '¿Qué energía rodea mi vida amorosa?',
                '¿Cómo está mi relación actual?',
                '¿Qué necesito sanar para abrirme al amor?',
            ], ['¿Es buen momento para empezar una relación?']],
            ['dinero_trabajo', 'Dinero y trabajo', '💰', [
                '¿Qué oportunidades laborales se acercan?',
                '¿Qué me frena en mi trabajo actual?',
                '¿Cómo puedo mejorar mi economía?',
            ], ['¿Es buen momento para un cambio laboral?']],
            ['bienestar', 'Bienestar', '🌿', [
                '¿Qué necesito para sentirme en equilibrio?',
                '¿Dónde estoy poniendo mi energía?',
            ], ['¿Es momento de priorizar mi descanso?']],
            ['crecimiento', 'Crecimiento personal', '🌱', [
                '¿Qué estoy aprendiendo en esta etapa?',
                '¿Qué tengo que soltar?',
                '¿Cuál es mi próximo paso?',
            ], ['¿Estoy en el camino correcto?']],
            ['general', 'General', '✦', [
                '¿Qué necesito saber hoy?',
                '¿Qué energía me acompaña este mes?',
            ], ['¿Me conviene tomar la decisión que estoy pensando?']],
        ];

        foreach ($temas as $orden => [$slug, $nombre, $icono, $tiradas, $siNo]) {
            $tema = Tema::updateOrCreate(['slug' => $slug], ['nombre' => $nombre, 'icono' => $icono, 'orden' => $orden]);
            $n = 0;
            foreach ([[$tiradas, self::TIRADAS], [$siNo, self::SI_NO]] as [$textos, $servicios]) {
                foreach ($textos as $texto) {
                    $tema->preguntas()->updateOrCreate(['texto' => $texto], ['servicios' => $servicios, 'activa' => true, 'orden' => $n++]);
                }
            }
        }
    }
}
