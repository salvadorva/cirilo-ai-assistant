<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $user_id
 * @property string $category
 * @property string $key
 * @property string $value
 * @property float  $confidence
 * @property string $source_type
 * @property \Carbon\Carbon|null $last_mentioned_at
 */
class UserProfileFact extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'key',
        'value',
        'confidence',
        'source_type',
        'last_mentioned_at',
    ];

    protected $casts = [
        'last_mentioned_at' => 'datetime',
        'confidence'        => 'float',
    ];

    // Categorías core: siempre inyectadas en el system prompt
    const CORE_CATEGORIES = ['personal_info', 'work_context', 'goals'];

    // Etiquetas legibles para la UI
    const CATEGORY_LABELS = [
        // Core — siempre en contexto
        'personal_info'  => 'Información Personal',
        'work_context'   => 'Contexto Laboral',
        'goals'          => 'Metas y Objetivos',
        // Intereses — inyección contextual por recencia/relevancia
        'preferences'    => 'Preferencias Generales',
        'relationships'  => 'Personas',
        'health'         => 'Salud y Bienestar',
        'sports'         => 'Deportes y Actividad Física',
        'studies'        => 'Estudios y Aprendizaje',
        'technology'     => 'Tecnología y Hobbies',
        'entertainment'  => 'Entretenimiento',
        'finance'        => 'Finanzas',
        'facts'          => 'Datos Varios',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
