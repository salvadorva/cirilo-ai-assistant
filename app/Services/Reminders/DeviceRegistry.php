<?php

namespace App\Services\Reminders;

use App\Models\DeviceInstallationClaim;
use App\Models\DeviceToken;
use App\Models\ReminderDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Registro de instalaciones compatibles (RC3). La identidad es installation_id,
 * no el token FCM: rotar el token actualiza la misma fila. Una instalación o un
 * token ligado a la instalación de otro usuario no se puede apropiar.
 */
class DeviceRegistry
{
    public function register(User $user, array $data): DeviceToken
    {
        return DB::transaction(function () use ($user, $data) {
            $byInstallation = DeviceToken::where('installation_id', $data['installation_id'])->lockForUpdate()->first();
            if ($byInstallation && $byInstallation->user_id !== $user->id) {
                throw $this->conflict();
            }
            $byToken = DeviceToken::where('token', $data['token'])->lockForUpdate()->first();
            if ($byToken && $byToken->installation_id && $byToken->installation_id !== $data['installation_id'] && $byToken->user_id !== $user->id) {
                throw $this->conflict();
            }
            if ($byToken && $byInstallation && $byToken->id !== $byInstallation->id) {
                $byToken->delete(); // el token pasó a esta instalación
            }
            $device = $byInstallation ?? $byToken ?? new DeviceToken;

            $device->fill([
                'user_id' => $user->id,
                'token' => $data['token'],
                'platform' => $data['platform'] ?? 'android',
                'installation_id' => $data['installation_id'],
                // Solo capacidades conocidas; omitirlas equivale a no tener ninguna (app antigua o degradada).
                'capabilities' => array_values(array_intersect(config('reminders.known_capabilities'), $data['capabilities'] ?? [])),
                'app_version' => $data['app_version'] ?? null,
                'last_used_at' => now(),
                'disabled_at' => null,
            ])->save();

            return $device;
        });
    }

    private function conflict(): ReminderApiException
    {
        return new ReminderApiException(409, 'installation_conflict',
            'La instalación pertenece a otra cuenta. Cierra sesión en ella o reclámala explícitamente desde este teléfono.');
    }

    /**
     * Reclamo explícito de una instalación ligada a otra cuenta (p. ej. cierre de
     * sesión sin red). Prueba de posesión: el token FCM vigente registrado para
     * esa instalación. Idempotente para el dueño actual. Transferir invalida los
     * despachos pendientes del dueño anterior hacia este teléfono.
     *
     * @return array{0: DeviceToken, 1: bool} dispositivo y si hubo transferencia
     */
    public function claim(User $user, array $data): array
    {
        $installation = $data['installation_id'];
        $limit = (int) config('reminders.claims.attempts_per_hour');
        $keys = ['reminders:claim:user:'.$user->id, 'reminders:claim:installation:'.hash('sha256', $installation)];
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $this->audit($installation, null, null, $user->id, 'rate_limited');
                throw new ReminderApiException(429, 'rate_limited', 'Demasiados intentos de reclamo; espera antes de reintentar.', [],
                    ['Retry-After' => (string) max(1, RateLimiter::availableIn($key))]);
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 3600);
        }

        $device = DeviceToken::where('installation_id', $installation)->first();
        if (! $device) {
            $this->audit($installation, null, null, $user->id, 'not_found');
            throw new ReminderApiException(404, 'installation_not_found', 'Instalación no registrada; usa el registro normal.');
        }
        if ($device->user_id === $user->id) {
            $this->audit($installation, $device->id, $user->id, $user->id, 'already_owned');

            return [$this->register($user, $data), false];
        }
        if (! hash_equals((string) $device->token, (string) $data['token'])) {
            $this->audit($installation, $device->id, $device->user_id, $user->id, 'denied_possession');
            throw new ReminderApiException(403, 'possession_not_proven', 'No se pudo comprobar la posesión del teléfono.');
        }

        $device = DB::transaction(function () use ($user, $data, $installation) {
            $device = DeviceToken::where('installation_id', $installation)->lockForUpdate()->firstOrFail();
            $previousOwner = $device->user_id;
            $device->forceFill([
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? $device->platform,
                'capabilities' => array_values(array_intersect(config('reminders.known_capabilities'), $data['capabilities'] ?? [])),
                'app_version' => $data['app_version'] ?? null,
                'last_used_at' => now(),
                'disabled_at' => null,
            ])->save();
            // Ningún envío pendiente del dueño anterior debe llegar al nuevo.
            app(ReminderDispatcher::class)->skipWaiting(ReminderDelivery::where('device_token_id', $device->id), 'installation_transferred');
            $this->audit($installation, $device->id, $previousOwner, $user->id, 'transferred');

            return $device;
        });
        Log::warning('device_installation.transferred', ['device_token_id' => $device->id, 'to_user_id' => $user->id]);

        return [$device, true];
    }

    /** Liberación por operador cuando el token rotó y no hay prueba de posesión posible. */
    public function release(string $installation, string $reason): ?DeviceToken
    {
        return DB::transaction(function () use ($installation, $reason) {
            $device = DeviceToken::where('installation_id', $installation)->lockForUpdate()->first();
            if (! $device) {
                return null;
            }
            app(ReminderDispatcher::class)->skipWaiting(ReminderDelivery::where('device_token_id', $device->id), 'installation_released');
            $this->audit($installation, $device->id, $device->user_id, null, 'operator_release', $reason);
            $device->delete();

            return $device;
        });
    }

    private function audit(string $installation, ?int $deviceId, ?int $from, ?int $to, string $outcome, ?string $reason = null): void
    {
        DeviceInstallationClaim::create(['installation_id' => $installation, 'device_token_id' => $deviceId, 'from_user_id' => $from,
            'to_user_id' => $to, 'outcome' => $outcome, 'reason' => $reason]);
    }
}
