<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $level
 * @property string $type
 * @property int $score
 * @property array|null $details
 * @property array|null $feedback
 * @property string|null $audio_url
 * @property string|null $exercise_content
 * @property string|null $user_answer
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $user
 */
class ExerciseResult extends Model
{
    protected $fillable = [
        'user_id',
        'level',
        'type',
        'score',
        'details',
        'feedback',
        'audio_url',
        'exercise_content',
        'user_answer',
    ];

    protected $casts = [
        'details' => 'array',
        'feedback' => 'array',
    ];

    /**
     * Obtiene el usuario al que pertenece este resultado
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
