<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property string $content
 * @property string|null $image_description
 * @property string|null $audio_script
 * @property array|null $practice_activity
 * @property int $session_order
 * @property string $status
 * @property string|null $image_path
 * @property \Carbon\Carbon|null $image_generated_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Course $course
 */
class CourseSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'content',
        'image_description',
        'audio_script',
        'practice_activity',
        'session_order',
        'status',
    ];

    protected $casts = [
        'practice_activity' => 'array',
    ];

    /**
     * Relación con el curso
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Relación con el progreso de los usuarios en esta sesión
     *
     * @return HasMany<CourseProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(CourseProgress::class, 'session_id');
    }
}
