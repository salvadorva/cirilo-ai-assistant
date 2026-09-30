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
// F3: cada minuto, porque ya no se adelantan avisos (antes: cada 5 min con 6 min de anticipación).
Schedule::command('agenda:schedule-notifications')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Programar recordatorios de eventos del calendario');

// Organización personal - Mensajes de enfoque por voz (push a la app Cirilo)
Schedule::command('focus:send-messages')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Enviar mensajes de enfoque por voz según focus_slots');

// Recordatorios contextuales y rutinas con despacho durable (RC2). No hace nada
// mientras REMINDERS_*_DISPATCH_ENABLED estén apagados.
Schedule::command('reminders:dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Despacho durable de recordatorios (claim + lease + reintentos)');

// Memoria (F4-03): resume conversaciones con mensajes nuevos inactivas 2h+. Estaba solo en
// app/Console/Kernel.php, que Laravel 11 no lee. Tope de llamadas por corrida en el comando.
Schedule::command('memory:summarize-stale')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Resumir conversaciones inactivas y extraer hechos del perfil');

// F6-05: resumen del día opcional (cada usuario elige hora, canal y días; desactivado por defecto).
Schedule::command('today:send-summary')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Resumen diario de agenda y pendientes');

// RC5: retención de recordatorios contextuales. Solo borra con REMINDERS_RETENTION_PURGE_ENABLED=true
// (requiere autorización); sin el flag no corre. Manual: `php artisan reminders:purge` informa.
Schedule::command('reminders:purge --apply')
    ->dailyAt('03:40')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('reminders.retention_purge_enabled'))
    ->description('Retención de recordatorios contextuales (contenido 7 días, metadatos 30)');

// RC5: sin worker permanente en el servidor, el scheduler vacía la cola cada minuto (despacho de
// recordatorios y audio). Termina al quedar vacía o a los 50 s; los reintentos los maneja el despachador.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1 --timeout=45')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->description('Procesar la cola (recordatorios contextuales)');

// IE1: las imágenes editadas se guardan 7 días.
Schedule::command('images:purge-edits')
    ->dailyAt('03:50')
    ->withoutOverlapping()
    ->description('Borrar imágenes editadas vencidas');
