<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role', // 'user', 'assistant'
        'content',
        'image_path', // Para mensajes con imágenes
    ];

    /**
     * Obtener la conversación a la que pertenece este mensaje.
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
