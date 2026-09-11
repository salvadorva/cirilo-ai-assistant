<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Slot de mensaje de enfoque: a la hora indicada (días configurados) se
 * envía push FCM data-only a la app con el mensaje, y si with_audio el
 * mp3 TTS. El audio se cachea en audio_path (lo genera focus:send-messages
 * una sola vez) y se invalida solo cuando cambia mensaje o voz.
 */
class FocusSlot extends Model
{
    protected $fillable = [
        'user_id', 'time', 'days', 'title', 'message', 'voice', 'with_audio', 'enabled', 'last_sent_at', 'audio_path',
    ];

    protected $casts = [
        'days' => 'array',
        'with_audio' => 'boolean',
        'enabled' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // El mp3 cacheado corresponde a un mensaje+voz exactos: si cambian, se invalida
        static::updating(function (FocusSlot $slot) {
            if (($slot->isDirty('message') || $slot->isDirty('voice')) && $slot->getOriginal('audio_path')) {
                Storage::disk('public')->delete($slot->getOriginal('audio_path'));
                $slot->audio_path = null;
            }
        });

        static::deleting(function (FocusSlot $slot) {
            if ($slot->audio_path) {
                Storage::disk('public')->delete($slot->audio_path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
