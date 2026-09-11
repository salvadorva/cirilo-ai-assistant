<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Engagement & Retention System - Task Scheduling
Schedule::command('engagement:detect-inactive --send-emails')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Detectar usuarios inactivos y enviar recordatorios');

Schedule::command('engagement:update-scores')
    ->everySixHours()
    ->withoutOverlapping()
    ->description('Actualizar scores de engagement de usuarios');

Schedule::command('engagement:send-weekly-reports')
    ->weeklyOn(0, '10:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Enviar reportes semanales a usuarios activos');

Schedule::command('engagement:update-scores --reset-weekly')
    ->weeklyOn(1, '00:00')
    ->description('Resetear contadores semanales');

// Gamification System - Achievement & Streak Management
Schedule::command('achievements:grant-retroactive')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Verificar y otorgar logros automáticamente cada hora');

Schedule::command('streaks:recalculate --all')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Recalcular rachas de todos los usuarios diariamente');

Schedule::command('admin:sync-data')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->description('Sincronizar datos del dashboard administrativo');

Schedule::command('audio:clean')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Limpiar archivos de audio no utilizados');

// Agenda - Recordatorios de eventos
Schedule::command('agenda:schedule-notifications')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Programar recordatorios de eventos del calendario');

// Organización personal - Mensajes de enfoque por voz (push a la app Cirilo)
Schedule::command('focus:send-messages')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Enviar mensajes de enfoque por voz según focus_slots');
