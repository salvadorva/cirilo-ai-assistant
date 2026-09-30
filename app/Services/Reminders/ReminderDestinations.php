<?php

namespace App\Services\Reminders;

use App\Models\DeviceToken;
use Illuminate\Support\Collection;

/**
 * Destinos elegibles. Hermes nunca elige destinatarios ni ve tokens FCM.
 */
class ReminderDestinations
{
    /** Contextuales: solo instalaciones compatibles y seleccionadas para el piloto. */
    public function contextual(int $userId): Collection
    {
        $allowlist = config('reminders.dispatch_device_allowlist', []);
        if ($allowlist === []) {
            return collect();
        }

        return DeviceToken::where('user_id', $userId)->whereNull('disabled_at')->whereIn('id', $allowlist)->orderBy('id')->get()
            ->filter(fn (DeviceToken $device) => $device->hasCapability(config('reminders.device_capability')))->values();
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
