<?php

namespace App\Jobs;

use App\Models\Lectura;
use App\Services\InterpretacionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Escribe el borrador de la interpretación fuera del request (proceso: sail artisan queue:work). */
class GenerarInterpretacion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // si falla, InterpretacionService ya cae en la base

    public int $timeout = 90;

    public function __construct(public int $lecturaId)
    {
        $this->afterCommit();
    }

    public function handle(InterpretacionService $interpretaciones): void
    {
        if ($lectura = Lectura::find($this->lecturaId)) {
            $interpretaciones->generar($lectura);
        }
    }
}
