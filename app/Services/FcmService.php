<?php

namespace App\Services;

use App\Models\DeviceToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private function getAccessToken(): string
    {
        $credentialsPath = storage_path('app/firebase-service-account.json');
        $credentials = new ServiceAccountCredentials(self::SCOPE, $credentialsPath);
        $token = $credentials->fetchAuthToken();

        return $token['access_token'];
    }

    private function getProjectId(): string
    {
        $json = json_decode(file_get_contents(storage_path('app/firebase-service-account.json')), true);

        return $json['project_id'];
    }

    /**
     * @param bool $dataOnly Envía mensaje solo-data (sin bloque notification):
     *                       la app construye su propia notificación incluso en
     *                       background. title/body viajan dentro de data.
     * @return bool true si al menos un token recibió el mensaje
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = [], bool $dataOnly = false): bool
    {
        $tokens = DeviceToken::where('user_id', $userId)->pluck('token');
        Log::info("[FCM] sendToUser({$userId}): tokens disponibles = {$tokens->count()}");

        $anySent = false;
        foreach ($tokens as $token) {
            $anySent = $this->sendToToken($token, $title, $body, $data, $dataOnly) || $anySent;
        }

        return $anySent;
    }

    public function sendToAll(string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::pluck('token');
        Log::info("[FCM] sendToAll: tokens disponibles = {$tokens->count()}");

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    private function sendToToken(string $token, string $title, string $body, array $data = [], bool $dataOnly = false): bool
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = sprintf(self::FCM_URL, $this->getProjectId());

            $message = [
                'token' => $token,
                'android' => [
                    'priority' => 'high',
                ],
            ];

            if ($dataOnly) {
                // title/body van dentro de data para que la app arme la notificación
                $data = array_merge(['title' => $title, 'body' => $body], $data);
            } else {
                $message['notification'] = [
                    'title' => $title,
                    'body'  => $body,
                ];
            }

            // FCM exige que `data` sea un map (objeto JSON), no una lista vacía
            if (! empty($data)) {
                $message['data'] = array_map('strval', $data);
            }

            $payload = ['message' => $message];

            $response = Http::withToken($accessToken)->post($url, $payload);

            $tokenPreview = substr($token, 0, 20).'...';

            if ($response->failed()) {
                $error = $response->json('error.message', 'unknown');

                // Token inválido o expirado — eliminarlo
                if (
                    str_contains($error, 'registration-token-not-registered') ||
                    str_contains($error, 'UNREGISTERED') ||
                    str_contains($error, 'Requested entity was not found') ||
                    str_contains($error, 'not a valid FCM registration token') ||
                    str_contains($error, 'INVALID_ARGUMENT')
                ) {
                    DeviceToken::where('token', $token)->delete();
                    Log::info("[FCM] Token eliminado por inválido ({$tokenPreview}): {$error}");
                } else {
                    Log::error("[FCM] Error enviando push a {$tokenPreview}: {$error}");
                }

                return false;
            }

            Log::info("[FCM] Enviado OK a {$tokenPreview}");

            return true;
        } catch (\Throwable $e) {
            Log::error('[FCM] Excepción: '.$e->getMessage().' en '.$e->getFile().':'.$e->getLine());

            return false;
        }
    }
}
