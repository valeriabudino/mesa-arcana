<?php

namespace App\Console\Commands;

use App\Services\OraculoService;
use Illuminate\Console\Command;

/** Revela la carta del día. Lo corre el scheduler a las 9 (hora argentina), o a mano para la demo. */
class RevelarOraculo extends Command
{
    protected $signature = 'oraculo:revelar
        {--otra : Si la de hoy ya se reveló, sortear otra (para la demo)}';

    protected $description = 'Revela el oráculo del día y lo avisa por el canal público';

    public function handle(OraculoService $oraculos): int
    {
        [$oraculo, $nuevo] = $oraculos->revelar((bool) $this->option('otra'));
        $carta = $oraculo->carta->nombre.($oraculo->invertida ? ' (invertida)' : '');

        $nuevo
            ? $this->info("Oráculo del {$oraculo->fecha->format('d/m')}: {$carta}.")
            : $this->comment("El oráculo de hoy ya estaba revelado: {$carta}. Usá --otra para sortear otra.");

        return self::SUCCESS;
    }
}
