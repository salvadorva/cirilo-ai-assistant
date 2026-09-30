<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Despacho durable por origen/ocurrencia/revisión/destino. Registra intento,
 * aceptación FCM y error por separado: aceptado no significa mostrado.
 */
class ReminderDelivery extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const RETRY_WAIT = 'retry_wait';

    public const ACCEPTED = 'accepted';

    public const FAILED = 'failed';

    public const UNCERTAIN = 'uncertain';

    public const SKIPPED = 'skipped';

    public const WAITING = [self::PENDING, self::RETRY_WAIT];

    protected $fillable = [
        'source_type', 'contextual_reminder_id', 'focus_slot_id', 'occurrence_key', 'revision', 'device_token_id',
        'destination_key', 'scheduled_at', 'expires_at', 'status', 'next_attempt_at',
    ];

    protected $casts = [
        'scheduled_at' => UtcDateTime::class,
        'expires_at' => UtcDateTime::class,
        'lease_expires_at' => UtcDateTime::class,
        'in_flight_since' => UtcDateTime::class,
        'enqueued_at' => UtcDateTime::class,
        'attempted_at' => UtcDateTime::class,
        'next_attempt_at' => UtcDateTime::class,
        'accepted_at' => UtcDateTime::class,
        'received_at' => UtcDateTime::class,
        'displayed_at' => UtcDateTime::class,
        'attempts' => 'integer',
        'uncertain_count' => 'integer',
        'revision' => 'integer',
    ];

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(ContextualReminder::class, 'contextual_reminder_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(FocusSlot::class, 'focus_slot_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class, 'device_token_id');
    }
}
