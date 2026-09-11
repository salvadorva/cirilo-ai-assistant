<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    /**
     * Verifica y otorga logros basados en las estadísticas del usuario
     */
    public function checkAndGrantAchievements(User $user)
    {
        $gameProgress = $user->gameProgress;
        if (! $gameProgress) {
            return;
        }

        // Obtener logros que el usuario aún no tiene
        $existingAchievementIds = $user->achievements()->pluck('achievements.id')->toArray();
        $availableAchievements = Achievement::whereNotIn('id', $existingAchievementIds)->get();

        $newlyGranted = [];

        foreach ($availableAchievements as $achievement) {
            if ($this->checkAchievementCondition($user, $achievement)) {
                $this->grantAchievement($user, $achievement);
                $newlyGranted[] = $achievement;
            }
        }

        return $newlyGranted;
    }

    /**
     * Verifica si el usuario cumple la condición para un logro
     */
    private function checkAchievementCondition(User $user, Achievement $achievement): bool
    {
        $gameProgress = $user->gameProgress;
        $sessions = DB::table('typing_sessions')->where('user_id', $user->id);

        switch ($achievement->key) {
            // Logros de bienvenida
            case 'first_login':
                return true; // Se otorga al crear cuenta (debe hacerse en registro)

            case 'first_typing_session':
                return $sessions->count() >= 1;

                // Logros de constancia
            case 'daily_user':
                return $gameProgress->current_streak >= 7;

            case 'dedicated_user':
                return $gameProgress->current_streak >= 30;

                // Logros de XP/Nivel
            case 'level_ten':
                return $gameProgress->level >= 10;

                // Logros de velocidad
            case 'speed_demon':
                $maxWpm = $sessions->max('wpm');

                return $maxWpm >= 60;

                // Logros de precisión
            case 'accuracy_perfectionist':
                $perfectSessions = $sessions->where('accuracy', '>=', 99)->count();

                return $perfectSessions >= 10;

                // Logros de horario
            case 'night_owl':
                // Verificar si ha jugado entre 00:00 y 06:00
                $nightSessions = DB::table('typing_sessions')
                    ->where('user_id', $user->id)
                    ->whereRaw('HOUR(session_date) >= 0 AND HOUR(session_date) < 6')
                    ->count();

                return $nightSessions >= 10;

            case 'early_bird':
                // Verificar si ha jugado entre 05:00 y 08:00
                $morningSessions = DB::table('typing_sessions')
                    ->where('user_id', $user->id)
                    ->whereRaw('HOUR(session_date) >= 5 AND HOUR(session_date) < 8')
                    ->count();

                return $morningSessions >= 10;

                // Logros de evaluación (del tutor de inglés)
            case 'first_evaluation':
                // Verificar si completó la evaluación inicial
                return DB::table('user_english_levels')->where('user_id', $user->id)->exists();

            case 'vocabulary_master':
                $level = DB::table('user_english_levels')->where('user_id', $user->id)->first();

                return $level && isset($level->vocabulary_score) && $level->vocabulary_score >= 90;

            case 'grammar_expert':
                $level = DB::table('user_english_levels')->where('user_id', $user->id)->first();

                return $level && isset($level->grammar_score) && $level->grammar_score >= 90;

            case 'speaking_star':
                $level = DB::table('user_english_levels')->where('user_id', $user->id)->first();

                return $level && isset($level->speaking_score) && $level->speaking_score >= 90;

            case 'listening_pro':
                $level = DB::table('user_english_levels')->where('user_id', $user->id)->first();

                return $level && isset($level->listening_score) && $level->listening_score >= 90;

            default:
                return false;
        }
    }

    /**
     * Otorga un logro a un usuario
     */
    private function grantAchievement(User $user, Achievement $achievement)
    {
        // Insertar en user_achievements
        DB::table('user_achievements')->insert([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
            'unlocked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Otorgar XP bonus
        if ($achievement->xp_reward > 0) {
            $user->gameProgress->addXP($achievement->xp_reward);
        }

        // Crear notificación
        Notification::create([
            'user_id' => $user->id,
            'type' => 'achievement',
            'title' => '¡Logro Desbloqueado!',
            'message' => "Has obtenido el logro: {$achievement->name}",
            'data' => json_encode([
                'achievement_id' => $achievement->id,
                'achievement_name' => $achievement->name,
                'xp_reward' => $achievement->xp_reward,
            ]),
            'is_read' => false,
        ]);
    }

    /**
     * Otorga logros retroactivos para usuarios existentes
     */
    public function grantRetroactiveAchievements(User $user)
    {
        return $this->checkAndGrantAchievements($user);
    }
}
