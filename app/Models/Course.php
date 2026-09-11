<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $level
 * @property int $sessions_count
 * @property int $user_id
 * @property string $status
 * @property string|null $image_description
 * @property string|null $audio_script
 * @property bool $created_by_ai
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseSession> $sessions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseProgress> $progress
 */
class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'level',
        'sessions_count',
        'user_id',
        'status',
        'image_description',
        'audio_script',
        'created_by_ai',
    ];

    protected $casts = [
        'created_by_ai' => 'boolean',
    ];

    /**
     * Relación con el usuario que creó el curso
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con las sesiones del curso
     *
     * @return HasMany<CourseSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class);
    }

    /**
     * Relación con el progreso de los usuarios
     *
     * @return HasMany<CourseProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(CourseProgress::class);
    }

    /**
     * Obtener el progreso de un usuario específico
     */
    public function progressForUser($userId)
    {
        return $this->progress()->where('user_id', $userId)->first();
    }
}
