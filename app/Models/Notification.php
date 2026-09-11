<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'is_important',
        'icon',
        'color',
        'action_url',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_important' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para notificaciones no leídas
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope para notificaciones importantes
     */
    public function scopeImportant($query)
    {
        return $query->where('is_important', true);
    }

    /**
     * Scope para notificaciones no expiradas
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Marcar como leída
     */
    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Crear notificación de logro desbloqueado
     */
    public static function createAchievementNotification($userId, $achievement)
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'achievement',
            'title' => '🏆 ¡Nuevo Logro Desbloqueado!',
            'message' => "Has desbloqueado el logro: {$achievement->name}",
            'data' => [
                'achievement_id' => $achievement->id,
                'xp_earned' => $achievement->xp_reward,
            ],
            'is_important' => true,
            'icon' => $achievement->icon,
            'color' => $achievement->badge_color,
            'action_url' => '/profile/achievements',
        ]);
    }

    /**
     * Crear notificación de inactividad
     */
    public static function createInactivityNotification($userId, $days)
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'inactivity',
            'title' => '😢 Te extrañamos',
            'message' => "Han pasado {$days} días desde tu última práctica. ¡Vuelve y continúa mejorando!",
            'data' => ['days_inactive' => $days],
            'icon' => 'fas fa-clock',
            'color' => '#ffc107',
            'action_url' => '/tutor',
        ]);
    }

    /**
     * Crear notificación de pérdida de ranking
     */
    public static function createRankLossNotification($userId, $newPosition, $category)
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'rank_loss',
            'title' => '📉 Cambio en tu Ranking',
            'message' => "Tu posición en {$category} ha cambiado al puesto #{$newPosition}. ¡Practica para recuperar tu lugar!",
            'data' => [
                'new_position' => $newPosition,
                'category' => $category,
            ],
            'icon' => 'fas fa-chart-line',
            'color' => '#dc3545',
            'action_url' => '/leaderboard',
        ]);
    }

    /**
     * Crear notificación de nivel subido
     */
    public static function createLevelUpNotification($userId, $newLevel)
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'level_up',
            'title' => '⭐ ¡Subiste de Nivel!',
            'message' => "¡Felicidades! Has alcanzado el nivel {$newLevel}",
            'data' => ['new_level' => $newLevel],
            'is_important' => true,
            'icon' => 'fas fa-star',
            'color' => '#ffd700',
            'action_url' => '/profile',
        ]);
    }
}
