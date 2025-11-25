<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mo:process')->everyMinute();

// Novo: rodar todo dia às 02h da manhã
Schedule::command('mo:daily-tasks')->dailyAt('02:00');

// Novo: rodar a cada 1 hora
Schedule::command('mo:hourly-tasks')->hourly();