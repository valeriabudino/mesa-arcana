<?php

use App\Services\OraculoService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// El oráculo del día se revela a las 9 de Argentina (proceso: sail artisan schedule:work).
Schedule::command('oraculo:revelar')->dailyAt(OraculoService::HORA)->timezone(OraculoService::ZONA);
