<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property bool $all_day
 * @property string|null $category
 * @property string|null $recurrence_type
 * @property \Carbon\Carbon|null $recurrence_end_date
 * @property string $status
 * @property string|null $color
 * @property string|null $location
 * @property int|null $reminder_minutes_before
 * @property bool $notified
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $user
 */
class CalendarEvent extends Model
{
    protected $fillable = [
        'user_id',
        'series_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'all_day',
        'category',
        'recurrence_type', // null, 'daily', 'weekly', 'monthly', 'yearly'
        'recurrence_end_date',
        'status', // 'pending', 'completed', 'cancelled'
        'color',
        'location',
        'reminder_minutes_before',
        'notified',
        'nextcloud_synced',
        'nextcloud_uid',
    ];

    protected $casts = [
        'start_date'         => 'datetime',
        'end_date'           => 'datetime',
        'recurrence_end_date'=> 'datetime',
        'all_day'            => 'boolean',
        'notified'           => 'boolean',
        'nextcloud_synced'   => 'boolean',
    ];

    public function getNextcloudUid(): string
    {
        return $this->nextcloud_uid ?? "asistente-{$this->id}@asistente.example.com";
    }

    /**
     * Obtiene el usuario al que pertenece este evento
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para eventos futuros
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', now());
    }

    /**
     * Scope para eventos que necesitan notificación
     */
    public function scopeNeedReminder($query)
    {
        $reminderTime = now()->addMinutes(10);

        return $query->where('start_date', '<=', $reminderTime)
            ->where('start_date', '>=', now())
            ->where('notified', false);
    }

    /**
     * Scope para eventos que están comenzando ahora
     */
    public function scopeStartingNow($query)
    {
        $startWindow = now()->subMinutes(1);
        $endWindow = now()->addMinutes(1);

        return $query->whereBetween('start_date', [$startWindow, $endWindow])
            ->where('notified', true); // Ya se envió la notificación previa
    }
}
