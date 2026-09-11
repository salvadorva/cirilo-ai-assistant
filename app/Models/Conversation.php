<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string|null $content
 * @property string $type
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messages
 */
class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'content',
        'type', // 'chat', 'creative', 'image_analysis'
        'summary',
        'summarized_message_count',
        'topics',
        'decisions',
        'pending_items',
    ];

    protected $casts = [
        'topics'        => 'array',
        'decisions'     => 'array',
        'pending_items' => 'array',
    ];

    /**
     * Obtener el usuario al que pertenece esta conversación.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtener los mensajes de esta conversación.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
