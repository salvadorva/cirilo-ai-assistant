<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'achievement_id',
        'unlocked_at',
        'unlock_data',
        'is_showcased',
    ];

    protected $casts = [
        'unlock_data' => 'array',
        'unlocked_at' => 'datetime',
        'is_showcased' => 'boolean',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el logro
     */
    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }

    /**
     * Scope para logros destacados por el usuario
     */
    public function scopeShowcased($query)
    {
        return $query->where('is_showcased', true);
    }

    /**
     * Scope para logros desbloqueados recientemente
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('unlocked_at', '>=', now()->subDays($days));
    }
}
