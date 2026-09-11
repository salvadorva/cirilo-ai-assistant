<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $level
 * @property int $lesson_number
 * @property string $content
 * @property array|null $focus_keys
 * @property int $target_wpm
 * @property float $target_accuracy
 * @property int $estimated_duration
 * @property array|null $prerequisites
 * @property string|null $instructions
 * @property bool $is_active
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserLessonProgress> $userProgress
 */
class TypingLesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'level',
        'lesson_number',
        'content',
        'focus_keys',
        'target_wpm',
        'target_accuracy',
        'estimated_duration',
        'prerequisites',
        'instructions',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'focus_keys' => 'array',
        'target_accuracy' => 'decimal:2',
        'prerequisites' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con el progreso de usuarios
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    /**
     * Scope para lecciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para lecciones de un nivel específico
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope para ordenar lecciones
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('level')->orderBy('lesson_number')->orderBy('sort_order');
    }

    /**
     * Verificar si el usuario puede acceder a esta lección
     */
    public function canUserAccess($userId)
    {
        if (empty($this->prerequisites)) {
            return true;
        }

        foreach ($this->prerequisites as $prerequisiteId) {
            $progress = UserLessonProgress::where('user_id', $userId)
                ->where('typing_lesson_id', $prerequisiteId)
                ->where('completed', true)
                ->first();

            if (! $progress) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtener el progreso del usuario para esta lección
     */
    public function getUserProgress($userId)
    {
        return $this->userProgress()->where('user_id', $userId)->first();
    }

    /**
     * Verificar si el usuario completó esta lección
     */
    public function isCompletedByUser($userId)
    {
        $progress = $this->getUserProgress($userId);

        return $progress && $progress->completed;
    }

    /**
     * Obtener duración formateada
     */
    public function getFormattedDurationAttribute()
    {
        $minutes = floor($this->estimated_duration / 60);
        $seconds = $this->estimated_duration % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Obtener nivel en español
     */
    public function getLevelInSpanishAttribute()
    {
        $levels = [
            'beginner' => 'Principiante',
            'intermediate' => 'Intermedio',
            'advanced' => 'Avanzado',
        ];

        return $levels[$this->level] ?? 'Desconocido';
    }
}
