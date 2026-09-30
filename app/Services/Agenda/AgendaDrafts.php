<?php

namespace App\Services\Agenda;

use App\Models\AgendaDraft;
use App\Models\User;

/**
 * F2-04: datos parciales de un evento por usuario y conversación, en lugar de un indicador de
 * sesión que afectaba a cualquier conversación. Caducan solos.
 */
class AgendaDrafts
{
    public const TTL_MINUTES = 30;

    /** Ámbitos del turno: la conversación y, en web, la sesión (el primer turno aún no tiene conversación). */
    public static function scopes(?int $conversationId, ?string $sessionId): array
    {
        return array_values(array_filter([
            $conversationId ? 'conversation:'.$conversationId : null,
            $sessionId ? 'session:'.substr(hash('sha256', $sessionId), 0, 40) : null,
        ]));
    }

    public function find(User $user, array $scopes): ?AgendaDraft
    {
        if ($scopes === []) {
            return null;
        }
        AgendaDraft::where('user_id', $user->id)->where('expires_at', '<', now())->delete();

        return AgendaDraft::where('user_id', $user->id)->whereIn('scope_key', $scopes)->latest('updated_at')->first();
    }

    public function save(User $user, string $scope, array $data, array $missing): AgendaDraft
    {
        return AgendaDraft::updateOrCreate(
            ['user_id' => $user->id, 'scope_key' => $scope],
            ['action' => 'create', 'data' => $data, 'missing' => $missing, 'expires_at' => now()->addMinutes(self::TTL_MINUTES)]
        );
    }

    public function discard(User $user, array $scopes): void
    {
        if ($scopes !== []) {
            AgendaDraft::where('user_id', $user->id)->whereIn('scope_key', $scopes)->delete();
        }
    }
}
