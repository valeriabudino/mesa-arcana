<?php

namespace App\Http\Controllers;

use App\Services\OraculoService;
use Illuminate\View\View;

/** El inicio: los dos caminos (consultante / tarotista) y el oráculo del día. Sin login. */
class InicioController extends Controller
{
    public function __invoke(OraculoService $oraculos): View
    {
        return view('welcome', [
            'oraculo' => [
                'revelado' => $oraculos->deHoyOPendiente()?->datosPublicos(),
                'proxima' => $oraculos->proximaRevelacion()->toIso8601String(),
            ],
        ]);
    }
}
