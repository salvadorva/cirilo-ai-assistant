<?php

namespace App\Services\Reminders;

use App\Models\DeviceToken;
use Illuminate\Support\Collection;

/**
 * Destinos elegibles. Hermes nunca elige destinatarios ni ve tokens FCM.
 */
class ReminderDestinations
{
    /**
     * Contextuales: instalaciones compatibles y activas del piloto.
     *
     * - Usuario en el piloto (REMINDERS_DISPATCH_USER_IDS): su instalación compatible usada más
     *   recientemente. Así reinstalar la app (que crea otra instalación) no saca al teléfono del
     *   piloto, y las instalaciones viejas que nunca se dieron de baja no reciben nada.
     * - Si no: las instalaciones elegidas una a una por ID (REMINDERS_DISPATCH_DEVICE_IDS).
     */
    public function contextual(int $userId): Collection
    {
        $users = config('reminders.dispatch_user_allowlist', []);
        $devices = config('reminders.dispatch_device_allowlist', []);
        $byUser = in_array($userId, $users, true);
        if (! $byUser && $devices === []) {
            return collect();
        }

        $compatible = DeviceToken::where('user_id', $userId)->whereNull('disabled_at')
            ->when(! $byUser, fn ($query) => $query->whereIn('id', $devices))
            ->orderByDesc('last_used_at')->orderByDesc('id')->get()
            ->filter(fn (DeviceToken $device) => $device->hasCapability(config('reminders.device_capability')));

        return $byUser ? $compatible->take(1)->values() : $compatible->sortBy('id')->values();
    }

    /** Rutinas: mismos destinos que el camino legado (todos los tokens del usuario), sin deshabilitados. */
    public function routine(int $userId): Collection
    {
        return DeviceToken::where('user_id', $userId)->whereNull('disabled_at')->orderBy('id')->get();
    }

    public function isContextualDestination(DeviceToken $device, int $userId): bool
    {
        return $this->contextual($userId)->contains('id', $device->id);
    }
}
