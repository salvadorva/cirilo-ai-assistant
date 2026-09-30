<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\Tasks\TodaySummary;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * F6-05: resumen del día opcional (desactivado por defecto), con hora, canal y días elegidos. Se
 * genera con datos reales (sin modelo) y solo se marca enviado si el canal lo aceptó; si falla,
 * se reintenta en el siguiente ciclo dentro de la ventana.
 */
class SendDailySummary extends Command
{
    protected $signature = 'today:send-summary';

    protected $description = 'Envía el resumen del día a quien lo activó';

    public const WINDOW_MINUTES = 15;

    public function handle(TodaySummary $today): int
    {
        $now = Carbon::now(config('app.timezone'));
        $sent = 0;

        User::where('daily_summary_enabled', true)->get()->each(function (User $user) use ($now, $today, &$sent) {
            if ($user->daily_summary_last_sent_on?->toDateString() === $now->toDateString()) {
                return;
            }
            if ($user->daily_summary_days === 'weekdays' && $now->isWeekend()) {
                return;
            }
            $at = Carbon::createFromFormat('Y-m-d H:i', $now->toDateString().' '.($user->daily_summary_time ?: '07:30'), config('app.timezone'));
            if ($now->lt($at) || $now->gt($at->copy()->addMinutes(self::WINDOW_MINUTES))) {
                return;
            }

            $text = $today->text($user);
            $accepted = match ($user->daily_summary_channel) {
                'telegram' => TelegramNotificationService::deliver($user, $text, ['type' => 'daily_summary'])['outcome'] === 'accepted',
                'email' => $this->email($user, $text),
                default => (bool) Notification::create(['user_id' => $user->id, 'type' => 'reminder', 'title' => 'Tu día de hoy',
                    'message' => $text, 'icon' => 'fas fa-sun', 'color' => '#f59e0b', 'is_important' => false, 'action_url' => '/hoy']),
            };

            if ($accepted) {
                $user->forceFill(['daily_summary_last_sent_on' => $now->toDateString()])->save();
                $sent++;
            } else {
                Log::warning('Resumen diario no aceptado por el canal; se reintenta en el siguiente ciclo', ['user_id' => $user->id, 'channel' => $user->daily_summary_channel]);
            }
        });

        $this->info("Resúmenes diarios enviados: {$sent}");

        return self::SUCCESS;
    }

    private function email(User $user, string $text): bool
    {
        if (! $user->email) {
            return false;
        }
        try {
            Mail::raw($text."\n\n".url('/hoy'), fn ($message) => $message->to($user->email)->subject('Tu día de hoy'));

            return true;
        } catch (\Throwable $e) {
            Log::error('Resumen diario por correo falló: '.$e->getMessage());

            return false;
        }
    }
}
