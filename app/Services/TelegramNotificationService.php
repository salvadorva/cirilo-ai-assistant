<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Envía un mensaje al usuario vía Telegram (n8n). Devuelve true solo si el webhook lo aceptó.
     *
     * @param  array  $extra  Datos adicionales: event_title, type, etc.
     */
    public static function send(User $user, string $message, array $extra = []): bool
    {
        return self::deliver($user, $message, $extra)['outcome'] === 'accepted';
    }

    /** El usuario tiene Telegram configurado y activo, y el webhook existe. */
    public static function available(User $user): bool
    {
        return (bool) ($user->telegram_chat_id && $user->telegram_notifications_enabled && config('services.n8n.telegram_webhook'));
    }

    /**
     * F3-03: resultado clasificado. accepted (2xx) | retry (429, 5xx, red o timeout) | rejected (otro 4xx) | skipped.
     * El webhook no es idempotente: un reintento tras un timeout puede duplicar el mensaje (política documentada).
     *
     * @return array{outcome: string, http_status: ?int}
     */
    public static function deliver(User $user, string $message, array $extra = []): array
    {
        if (! $user->telegram_chat_id || ! $user->telegram_notifications_enabled) {
            return ['outcome' => 'skipped', 'http_status' => null];
        }

        $webhookUrl = config('services.n8n.telegram_webhook');
        $secret     = config('services.n8n.webhook_secret');

        if (! $webhookUrl) {
            Log::warning('TelegramNotificationService: N8N_TELEGRAM_WEBHOOK_URL no configurado — omitiendo envío', [
                'user_id' => $user->id,
            ]);

            return ['outcome' => 'skipped', 'http_status' => null];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Webhook-Secret' => $secret])
                ->post($webhookUrl, array_merge(['chat_id' => $user->telegram_chat_id, 'message' => $message], $extra));
        } catch (\Exception $e) {
            Log::error('TelegramNotificationService: excepción al llamar webhook', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return ['outcome' => 'retry', 'http_status' => null];
        }

        $status = $response->status();
        if ($response->successful()) {
            Log::info('TelegramNotificationService: mensaje enviado', ['user_id' => $user->id, 'type' => $extra['type'] ?? 'general']);

            return ['outcome' => 'accepted', 'http_status' => $status];
        }

        // Sin el cuerpo de la respuesta ni el chat_id en el log.
        Log::error('TelegramNotificationService: respuesta no exitosa del webhook', ['user_id' => $user->id, 'status' => $status]);

        return ['outcome' => $status === 429 || $status >= 500 ? 'retry' : 'rejected', 'http_status' => $status];
    }
}
