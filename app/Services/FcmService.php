<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Services\Reminders\Push\PushResult;
use App\Services\Reminders\Push\PushTransport;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService implements PushTransport
{
    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    protected function getAccessToken(): string
    {
        if (app()->environment('testing')) {
            throw new \LogicException('FCM credentials/network are disabled in tests; inject a fake FcmService.');
        }
        $credentialsPath = storage_path('app/firebase-service-account.json');
        $credentials = new ServiceAccountCredentials(self::SCOPE, $credentialsPath);
        $token = $credentials->fetchAuthToken();

        return $token['access_token'];
    }

    protected function getProjectId(): string
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

    /**
     * Envío data-only a un destino con resultado estructurado (RC2). No borra
     * tokens ni registra el token ni el contenido: la decisión la toma el
     * despachador. sendToUser() se conserva para los consumidores existentes.
     */
    public function send(DeviceToken $device, array $data, int $ttlSeconds): PushResult
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = sprintf(self::FCM_URL, $this->getProjectId());
        } catch (\Throwable $e) {
            // Nada salió hacia FCM: fallo de configuración, sin reintento rápido.
            return PushResult::auth(null, 'credentials');
        }

        $payload = ['message' => [
            'token' => $device->token,
            'data' => array_map('strval', $data),
            'android' => ['priority' => 'high', 'ttl' => max(0, $ttlSeconds).'s'],
        ]];

        try {
            $response = Http::withToken($accessToken)
                ->timeout((int) config('reminders.dispatch.http_timeout', 20))
                ->connectTimeout((int) config('reminders.dispatch.connect_timeout', 5))
                ->post($url, $payload);
        } catch (ConnectionException) {
            return PushResult::uncertain('timeout');
        }

        return self::classify($response);
    }

    /** Clasificación por status HTTP y códigos estructurados de FCM v1, no por texto libre. */
    public static function classify(Response $response): PushResult
    {
        $status = $response->status();
        if ($response->successful()) {
            $name = $response->json('name');

            return is_string($name) && $name !== '' ? PushResult::accepted($name, $status) : PushResult::uncertain('missing_message_id');
        }

        $fcmCode = null;
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (is_array($detail) && str_ends_with((string) ($detail['@type'] ?? ''), 'google.firebase.fcm.v1.FcmError')) {
                $fcmCode = $detail['errorCode'] ?? null;
            }
        }
        $grpc = $response->json('error.status');
        $retryAfter = is_numeric($response->header('Retry-After')) ? (int) $response->header('Retry-After') : null;

        return match (true) {
            $fcmCode === 'UNREGISTERED' => PushResult::unregistered($status),
            $fcmCode === 'INVALID_ARGUMENT' || $grpc === 'INVALID_ARGUMENT' => PushResult::invalidPayload($status),
            $fcmCode === 'SENDER_ID_MISMATCH' => PushResult::rejected($status, 'sender_id_mismatch'),
            $fcmCode === 'THIRD_PARTY_AUTH_ERROR', in_array($status, [401, 403], true) => PushResult::auth($status),
            $fcmCode === 'QUOTA_EXCEEDED' || $status === 429 => PushResult::retryable($status, 'quota_exceeded', $retryAfter),
            $fcmCode === 'UNAVAILABLE' || $status === 503 => PushResult::retryable($status, 'unavailable', $retryAfter),
            $fcmCode === 'INTERNAL' || $status >= 500 => PushResult::retryable($status, 'internal', $retryAfter),
            default => PushResult::rejected($status, 'rejected'),
        };
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

            // Huella no reversible: el token FCM sirve como prueba de posesión del teléfono.
            $tokenPreview = 'sha256:'.substr(hash('sha256', $token), 0, 12);

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
