<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pendiente del usuario (F6-02). Una tarea sin fecha no ocupa tiempo en la agenda. */
class Task extends Model
{
    public const STATUSES = ['suggested', 'open', 'postponed', 'done', 'dismissed'];

    protected $fillable = ['user_id', 'title', 'notes', 'status', 'due_date', 'postponed_until', 'source', 'source_conversation_id',
        'calendar_event_id', 'completed_at'];

    protected $casts = ['due_date' => 'date', 'postponed_until' => 'date', 'completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
