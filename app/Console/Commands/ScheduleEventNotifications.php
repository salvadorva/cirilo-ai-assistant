<?php

namespace App\Console\Commands;

use App\Models\EventNotificationDelivery;
use App\Services\Agenda\EventNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * F3: avisos de agenda (recordatorio previo e inicio) con estado por canal. Ver EventNotifier.
 */
class ScheduleEventNotifications extends Command
{
    protected $signature = 'agenda:schedule-notifications {--status : Muestra el estado de los avisos sin enviar nada}';

    protected $description = 'Envía recordatorios e inicios de eventos por canal (interno, correo, Telegram, FCM)';

    public const HEARTBEAT_KEY = 'agenda:notifications:last_run';

    public function handle(EventNotifier $notifier): int
    {
        if ($this->option('status')) {
            return $this->status();
        }

        $started = microtime(true);
        $summary = $notifier->run();
        Cache::put(self::HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay());

        $line = collect($summary)->map(fn ($n, $k) => "{$k}={$n}")->implode(' ');
        $this->info("Avisos de agenda: {$line}");
        if (array_sum($summary) > 0) {
            Log::info('agenda:schedule-notifications', $summary + ['ms' => (int) ((microtime(true) - $started) * 1000)]);
        }

        return self::SUCCESS;
    }

    /** F3-04: última ejecución, pendientes atrasados y fallos recientes. */
    private function status(): int
    {
        $last = Cache::get(self::HEARTBEAT_KEY);
        $this->line('Última ejecución: '.($last ?? 'sin registro'));
        $overdue = EventNotificationDelivery::whereIn('status', EventNotificationDelivery::WAITING)->where('next_attempt_at', '<', now()->subMinutes(2))->count();
        $this->line("Pendientes atrasados (> 2 min): {$overdue}");
        $rows = EventNotificationDelivery::where('updated_at', '>=', now()->subDay())
            ->selectRaw('channel, status, count(*) as total')->groupBy('channel', 'status')->orderBy('channel')->get()
            ->map(fn ($r) => [$r->channel, $r->status, $r->total])->all();
        $this->table(['Canal', 'Estado', 'Últimas 24 h'], $rows);

        return self::SUCCESS;
    }
}
