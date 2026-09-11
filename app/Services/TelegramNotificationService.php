<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Envía una notificación a Telegram via webhook n8n.
     *
     * @param  array  $extra  Datos adicionales: event_title, type, etc.
     */
    public static function send(User $user, string $message, array $extra = []): bool
    {
        // Verificar que el usuario tiene Telegram configurado y habilitado
        if (! $user->telegram_chat_id || ! $user->telegram_notifications_enabled) {
            return false;
        }

        $webhookUrl = config('services.n8n.telegram_webhook');
        $secret     = config('services.n8n.webhook_secret');

        if (! $webhookUrl) {
            Log::warning('TelegramNotificationService: N8N_TELEGRAM_WEBHOOK_URL no configurado — omitiendo envío', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        $payload = array_merge([
            'chat_id' => $user->telegram_chat_id,
            'message' => $message,
        ], $extra);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Webhook-Secret' => $secret])
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('TelegramNotificationService: mensaje enviado', [
                    'user_id'  => $user->id,
                    'chat_id'  => $user->telegram_chat_id,
                    'type'     => $extra['type'] ?? 'general',
                ]);
                return true;
            }

            Log::error('TelegramNotificationService: respuesta no exitosa del webhook', [
                'user_id' => $user->id,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('TelegramNotificationService: excepción al llamar webhook', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}
