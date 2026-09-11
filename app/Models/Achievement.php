<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'icon',
        'category',
        'rarity',
        'xp_reward',
        'conditions',
        'badge_color',
        'is_secret',
        'is_active',
        'order',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_secret' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con los usuarios que han desbloqueado este logro
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at', 'unlock_data', 'is_showcased')
            ->withTimestamps();
    }

    /**
     * Relación con los registros de logros de usuario
     */
    public function userAchievements()
    {
        return $this->hasMany(UserAchievement::class);
    }

    /**
     * Scope para logros activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para logros por categoría
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope para logros públicos (no secretos)
     */
    public function scopePublic($query)
    {
        return $query->where('is_secret', false);
    }

    /**
     * Verificar si un usuario ha desbloqueado este logro
     */
    public function isUnlockedBy($userId)
    {
        return $this->users()->where('user_id', $userId)->exists();
    }

    /**
     * Verificar si se cumplen las condiciones para desbloquear
     */
    public function checkConditions($userData)
    {
        $conditions = $this->conditions;

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '>=';
            $value = $condition['value'] ?? 0;

            if (! $field || ! isset($userData[$field])) {
                return false;
            }

            $userValue = $userData[$field];

            switch ($operator) {
                case '>=':
                    if ($userValue < $value) {
                        return false;
                    }
                    break;
                case '>':
                    if ($userValue <= $value) {
                        return false;
                    }
                    break;
                case '=':
                case '==':
                    if ($userValue != $value) {
                        return false;
                    }
                    break;
                case '<=':
                    if ($userValue > $value) {
                        return false;
                    }
                    break;
                case '<':
                    if ($userValue >= $value) {
                        return false;
                    }
                    break;
                default:
                    return false;
            }
        }

        return true;
    }

    /**
     * Obtener el color del badge según la rareza
     */
    public function getRarityColorAttribute()
    {
        return match ($this->rarity) {
            'common' => '#6c757d',
            'rare' => '#007bff',
            'epic' => '#6f42c1',
            'legendary' => '#ffd700',
            default => '#6c757d'
        };
    }

    /**
     * Obtener estadísticas del logro
     */
    public function getStatsAttribute()
    {
        $totalUsers = User::count();
        $unlockedCount = $this->userAchievements()->count();

        return [
            'total_unlocked' => $unlockedCount,
            'unlock_percentage' => $totalUsers > 0 ? round(($unlockedCount / $totalUsers) * 100, 2) : 0,
            'rarity_score' => $this->calculateRarityScore($unlockedCount, $totalUsers),
        ];
    }

    /**
     * Calcular puntuación de rareza
     */
    private function calculateRarityScore($unlockedCount, $totalUsers)
    {
        if ($totalUsers == 0) {
            return 0;
        }

        $percentage = ($unlockedCount / $totalUsers) * 100;

        if ($percentage >= 75) {
            return 1;
        } // Muy común
        if ($percentage >= 50) {
            return 2;
        } // Común
        if ($percentage >= 25) {
            return 3;
        } // Poco común
        if ($percentage >= 10) {
            return 4;
        } // Raro
        if ($percentage >= 5) {
            return 5;
        }  // Muy raro

        return 6; // Legendario
    }
}
