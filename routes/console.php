<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatoris de registre (docs/com-funcionen-les-alertes.md, secció 10). Cal que el servidor executi
// `php artisan schedule:run` cada minut (cron); sense això, cap tasca programada s'executa mai.
Schedule::command('reminders:morning')->dailyAt('07:30')->timezone('Europe/Madrid');
Schedule::command('reminders:evening')->dailyAt('21:30')->timezone('Europe/Madrid');
