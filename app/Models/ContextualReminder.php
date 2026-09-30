<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recordatorio puntual acordado entre Salva y Hermes. Su UUID es también
 * occurrence_id. Separado de FocusSlot (rutinas) a propósito.
 */
class ContextualReminder extends Model
{
    use HasUuids;

    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    protected $fillable = [
        'user_id', 'reminder_integration_id', 'title', 'context', 'next_action', 'scheduled_at', 'expires_at',
        'timezone', 'with_audio', 'voice', 'state', 'version', 'confirmed_by_user', 'confirmed_at',
        'completed_at', 'cancelled_at', 'expired_at', 'closed_by',
    ];

    protected $hidden = ['audio_path'];

    protected $casts = [
        'scheduled_at' => UtcDateTime::class,
        'expires_at' => UtcDateTime::class,
        'confirmed_at' => UtcDateTime::class,
        'completed_at' => UtcDateTime::class,
        'cancelled_at' => UtcDateTime::class,
        'expired_at' => UtcDateTime::class,
        'with_audio' => 'boolean',
        'confirmed_by_user' => 'boolean',
        'version' => 'integer',
    ];

    /** Estado visible: un pendiente fuera de su ventana ya no es pendiente aunque no haya corrido el scheduler. */
    public function effectiveState(): string
    {
        return $this->state === self::PENDING && $this->isPastExpiry() ? self::EXPIRED : $this->state;
    }

    public function isPastExpiry(): bool
    {
        return ! now()->lt($this->expires_at);
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ReminderIntegration::class, 'reminder_integration_id');
    }
}
