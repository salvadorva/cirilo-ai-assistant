<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Integración externa (Hermes) vinculada a un único propietario.
 * La credencial opaca solo existe en claro al emitirla/rotarla.
 */
class ReminderIntegration extends Model
{
    public const TOKEN_PREFIX = 'cirilo_hrm_';

    public const SCOPES = ['reminders:create', 'reminders:read', 'reminders:cancel', 'reminders:snooze', 'reminders:complete'];

    protected $fillable = ['user_id', 'name', 'token_hash', 'scopes', 'expires_at', 'revoked_at', 'last_used_at'];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /** @return array{0: self, 1: string} Integración y credencial en claro (mostrar una sola vez). */
    public static function issue(User $owner, string $name, array $scopes = self::SCOPES, ?\DateTimeInterface $expiresAt = null): array
    {
        $token = self::newToken();
        $integration = self::create([
            'user_id' => $owner->id,
            'name' => $name,
            'token_hash' => self::hashToken($token),
            'scopes' => array_values(array_intersect($scopes, self::SCOPES)),
            'expires_at' => $expiresAt,
        ]);

        return [$integration, $token];
    }

    /** Sustituye la credencial conservando identidad y espacio de idempotencia. */
    public function rotate(): string
    {
        $token = self::newToken();
        $this->forceFill(['token_hash' => self::hashToken($token)])->save();

        return $token;
    }

    public static function findActiveByToken(string $token): ?self
    {
        if (! str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }
        $integration = self::where('token_hash', self::hashToken($token))->first();
        if (! $integration || ! hash_equals($integration->token_hash, self::hashToken($token))) {
            return null;
        }
        if ($integration->revoked_at || ($integration->expires_at && $integration->expires_at->isPast())) {
            return null;
        }

        return $integration;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    private static function newToken(): string
    {
        return self::TOKEN_PREFIX.bin2hex(random_bytes(32));
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
