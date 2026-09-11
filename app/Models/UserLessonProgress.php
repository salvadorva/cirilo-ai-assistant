<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $typing_lesson_id
 * @property bool $completed
 * @property int $best_wpm
 * @property float $best_accuracy
 * @property int $attempts
 * @property \Carbon\Carbon|null $first_attempt
 * @property \Carbon\Carbon|null $completed_at
 * @property array|null $attempt_history
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\TypingLesson $typingLesson
 */
class UserLessonProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'typing_lesson_id',
        'completed',
        'best_wpm',
        'best_accuracy',
        'attempts',
        'first_attempt',
        'completed_at',
        'attempt_history',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'best_accuracy' => 'decimal:2',
        'first_attempt' => 'datetime',
        'completed_at' => 'datetime',
        'attempt_history' => 'array',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con la lección
     */
    public function typingLesson(): BelongsTo
    {
        return $this->belongsTo(TypingLesson::class);
    }

    /**
     * Registrar un nuevo intento
     */
    public function recordAttempt($wpm, $accuracy, $completed = false)
    {
        $this->attempts++;

        if (! $this->first_attempt) {
            $this->first_attempt = now();
        }

        // Actualizar mejores marcas
        if ($wpm > $this->best_wpm) {
            $this->best_wpm = $wpm;
        }

        if ($accuracy > $this->best_accuracy) {
            $this->best_accuracy = $accuracy;
        }

        // Marcar como completado si se cumplen los objetivos
        if ($completed && ! $this->completed) {
            $this->completed = true;
            $this->completed_at = now();
        }

        // Agregar al historial
        $history = $this->attempt_history ?: [];
        $history[] = [
            'wpm' => $wpm,
            'accuracy' => $accuracy,
            'completed' => $completed,
            'timestamp' => now()->toISOString(),
        ];

        // Mantener solo los últimos 10 intentos
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }

        $this->attempt_history = $history;
        $this->save();

        return $this;
    }

    /**
     * Verificar si cumple los objetivos de la lección
     */
    public function meetsTargets()
    {
        $lesson = $this->typingLesson;

        return $this->best_wpm >= $lesson->target_wpm &&
               $this->best_accuracy >= $lesson->target_accuracy;
    }

    /**
     * Obtener progreso en porcentaje
     */
    public function getProgressPercentage()
    {
        if ($this->completed) {
            return 100;
        }

        $lesson = $this->typingLesson;
        $wpmProgress = min(100, ($this->best_wpm / $lesson->target_wpm) * 100);
        $accuracyProgress = min(100, ($this->best_accuracy / $lesson->target_accuracy) * 100);

        return round(($wpmProgress + $accuracyProgress) / 2);
    }
}
