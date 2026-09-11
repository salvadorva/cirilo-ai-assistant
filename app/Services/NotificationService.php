<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Crear notificación de logro desbloqueado
     */
    public static function achievementUnlocked(User $user, $achievementName, $xpGained = 0)
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'achievement',
            'title' => '🎉 ¡Logro desbloqueado!',
            'message' => "Has desbloqueado: {$achievementName}",
            'icon' => 'fas fa-trophy',
            'color' => '#f093fb',
            'is_important' => true,
            'action_url' => '/typing',
            'data' => json_encode([
                'achievement_name' => $achievementName,
                'xp_gained' => $xpGained,
            ]),
        ]);
    }

    /**
     * Crear notificación de nivel alcanzado
     */
    public static function levelUp(User $user, $oldLevel, $newLevel)
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'achievement',
            'title' => '🚀 ¡Subiste de nivel!',
            'message' => "Ahora eres nivel {$newLevel}. ¡Sigue así!",
            'icon' => 'fas fa-arrow-up',
            'color' => '#667eea',
            'is_important' => true,
            'action_url' => '/typing/stats',
            'data' => json_encode([
                'old_level' => $oldLevel,
                'new_level' => $newLevel,
            ]),
        ]);
    }

    /**
     * Crear notificación de lección completada
     */
    public static function lessonCompleted(User $user, $lessonName, $lessonId = null)
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'lesson',
            'title' => '📚 ¡Lección completada!',
            'message' => "Has completado la lección: {$lessonName}",
            'icon' => 'fas fa-book',
            'color' => '#4facfe',
            'action_url' => '/typing/lessons',
            'data' => json_encode([
                'lesson_id' => $lessonId,
                'lesson_name' => $lessonName,
            ]),
        ]);
    }

    /**
     * Crear notificación de nueva lección disponible
     */
    public static function newLessonAvailable(User $user, $lessonName, $lessonId = null)
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'lesson',
            'title' => '🆕 Nueva lección disponible',
            'message' => "Prueba la nueva lección: {$lessonName}",
            'icon' => 'fas fa-book-open',
            'color' => '#00d4ff',
            'action_url' => '/typing/lessons',
            'data' => json_encode([
                'lesson_id' => $lessonId,
                'lesson_name' => $lessonName,
            ]),
        ]);
    }

    /**
     * Crear notificación de recordatorio de inactividad
     */
    public static function inactivityReminder(User $user, $daysInactive)
    {
        $message = $daysInactive === 1
            ? '¡Te extrañamos! No has practicado hoy.'
            : "Llevas {$daysInactive} días sin practicar. ¡Vuelve!";

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'reminder',
            'title' => '⏰ ¡Es hora de practicar!',
            'message' => $message,
            'icon' => 'fas fa-clock',
            'color' => '#fa709a',
            'is_important' => true,
            'action_url' => '/typing',
            'data' => json_encode([
                'days_inactive' => $daysInactive,
            ]),
        ]);
    }

    /**
     * Crear notificación de racha activa
     */
    public static function streakMilestone(User $user, $streakDays)
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'achievement',
            'title' => '🔥 ¡Racha increíble!',
            'message' => "Llevas {$streakDays} días consecutivos practicando. ¡Impresionante!",
            'icon' => 'fas fa-fire',
            'color' => '#ff6b6b',
            'is_important' => true,
            'action_url' => '/typing/stats',
            'data' => json_encode([
                'streak_days' => $streakDays,
            ]),
        ]);
    }

    /**
     * Crear notificación de recompensa ganada
     */
    public static function rewardEarned(User $user, $rewardType, $amount, $reason = '')
    {
        $message = "Has ganado {$amount} XP";
        if ($reason) {
            $message .= " por {$reason}";
        }

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'reward',
            'title' => '🎁 ¡Recompensa ganada!',
            'message' => $message,
            'icon' => 'fas fa-gift',
            'color' => '#ffa502',
            'action_url' => '/typing/rewards',
            'data' => json_encode([
                'reward_type' => $rewardType,
                'amount' => $amount,
                'reason' => $reason,
            ]),
        ]);
    }

    /**
     * Crear notificación de récord personal batido
     */
    public static function personalRecord(User $user, $metric, $oldValue, $newValue)
    {
        $metricNames = [
            'wpm' => 'Palabras por minuto',
            'accuracy' => 'Precisión',
            'score' => 'Puntuación',
        ];

        $metricName = $metricNames[$metric] ?? $metric;

        return Notification::create([
            'user_id' => $user->id,
            'type' => 'achievement',
            'title' => '🏆 ¡Nuevo récord personal!',
            'message' => "Has superado tu récord de {$metricName}: {$newValue}",
            'icon' => 'fas fa-medal',
            'color' => '#ffd700',
            'is_important' => true,
            'action_url' => '/typing/stats',
            'data' => json_encode([
                'metric' => $metric,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]),
        ]);
    }

    /**
     * Crear notificación de actualización del sistema
     */
    public static function systemUpdate(User $user, $title, $message, $features = [])
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => "⚙️ {$title}",
            'message' => $message,
            'icon' => 'fas fa-cog',
            'color' => '#43e97b',
            'action_url' => '/typing',
            'data' => json_encode([
                'features' => $features,
            ]),
        ]);
    }

    /**
     * Crear notificación personalizada
     */
    public static function custom(User $user, array $data)
    {
        return Notification::create(array_merge([
            'user_id' => $user->id,
            'type' => $data['type'] ?? 'system',
            'icon' => $data['icon'] ?? 'fas fa-bell',
            'color' => $data['color'] ?? '#667eea',
            'is_important' => $data['is_important'] ?? false,
            'action_url' => $data['action_url'] ?? null,
        ], $data));
    }

    /**
     * Notificar a todos los usuarios (broadcast)
     */
    public static function broadcast($title, $message, $type = 'system', $actionUrl = null)
    {
        $users = User::all();
        $created = 0;

        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'icon' => 'fas fa-bullhorn',
                'color' => '#667eea',
                'is_important' => true,
                'action_url' => $actionUrl,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Limpiar notificaciones antiguas leídas
     */
    public static function cleanupOld($daysOld = 30)
    {
        return Notification::where('is_read', true)
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Verificar si un usuario tiene habilitadas las notificaciones por email
     */
    public static function canSendEmail(User $user): bool
    {
        return $user->email_notifications_enabled ?? true;
    }

    /**
     * Enviar notificación por email si el usuario lo tiene habilitado
     * (Método de ejemplo para integración futura con sistema de emails)
     */
    public static function sendEmailIfEnabled(User $user, string $subject, string $message)
    {
        if (! self::canSendEmail($user)) {
            return false;
        }

        // Aquí se integraría con el sistema de emails de Laravel
        // Mail::to($user->email)->send(new NotificationMail($subject, $message));

        return true;
    }
}
