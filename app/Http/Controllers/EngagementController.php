<?php

namespace App\Http\Controllers;

use App\Models\UserActivityTracker;
use App\Models\UserGameProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EngagementController extends Controller
{
    /**
     * Dashboard de Engagement - Unificado para todos los juegos
     */
    /**
     * Dashboard de Engagement - Unificado para todos los juegos
     */
    public function dashboard()
    {
        $user = Auth::user();
        $progress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        // Actualizar métricas antes de mostrar
        $progress->updateChurnRiskLevel();

        // --- Lógica de AI Coach (Nexus) ---
        $cacheKey = 'gamification_summary_'.$user->id;
        $aiSummary = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (! $aiSummary) {
            // Si no hay resumen en caché, generarlo (o intentar)
            // Recopilar contexto para la IA
            $context = [
                'name' => $user->name,
                'level' => $progress->level,
                'total_xp' => $progress->total_xp,
                'days_offline' => $user->last_login_at ? now()->diffInDays($user->last_login_at) : 0,
                'recent_activity' => $this->getRecentActivitySummary($user->id),
                'age' => $user->age,
            ];

            // Llamar al AIController
            $aiController = new \App\Http\Controllers\AIController;
            $request = new Request(['context' => $context]);
            $response = $aiController->generateGamificationSummary($request);
            $data = json_decode($response->getContent(), true);

            if ($data['success']) {
                $aiSummary = [
                    'message' => $data['message'],
                    'audioUrl' => $data['audioUrl'],
                    'generated_at' => now()->toIso8601String(),
                ];
                // Guardar en caché por 1 hora
                \Illuminate\Support\Facades\Cache::put($cacheKey, $aiSummary, 3600);
            }
        }

        // --- Fin Lógica AI Coach ---

        // Obtener actividades recientes (de todos los juegos)
        $recentActivities = UserActivityTracker::where('user_id', $user->id)
            ->orderBy('session_start', 'desc')
            ->limit(15)
            ->get();

        // ... (resto del código existente para fallbackEnglish y estadísticas) ...

        // Asegurar que aparezcan actividades de juegos de inglés aunque falten en el tracker
        if (! $recentActivities->contains(fn ($activity) => $activity->activity_type === 'english_games')) {
            $fallbackEnglish = DB::table('english_game_sessions')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(function ($session) {
                    return (object) [
                        'session_start' => $session->created_at,
                        'activity_type' => 'english_games',
                        'session_duration_seconds' => $session->time_spent,
                        'xp_earned' => $this->calculateGameXP($session),
                        'activity_quality' => $this->mapQualityFromDuration($session->time_spent),
                    ];
                });

            if ($fallbackEnglish->isNotEmpty()) {
                $recentActivities = $recentActivities
                    ->concat($fallbackEnglish)
                    ->sortByDesc(function ($activity) {
                        return $activity->session_start instanceof \Carbon\Carbon
                            ? $activity->session_start
                            : \Carbon\Carbon::parse($activity->session_start);
                    })
                    ->take(15)
                    ->values();
            }
        }

        // Estadísticas de la semana actual
        $startOfWeek = \Carbon\Carbon::now()->startOfWeek();
        $weekActivities = UserActivityTracker::where('user_id', $user->id)
            ->where('session_start', '>=', $startOfWeek)
            ->get();

        // Análisis de calidad de sesiones (usar campo activity_quality)
        $qualityDistribution = $weekActivities->groupBy('activity_quality')->map->count();

        // Progreso diario de la semana
        $dailyProgress = [];
        for ($i = 0; $i < 7; $i++) {
            $day = \Carbon\Carbon::now()->startOfWeek()->addDays($i);
            $dayActivities = $weekActivities->filter(function ($activity) use ($day) {
                return \Carbon\Carbon::parse($activity->session_start)->isSameDay($day);
            });

            $dailyProgress[] = [
                'day' => $day->format('D'),
                'date' => $day->format('Y-m-d'),
                'sessions' => $dayActivities->count(),
                'xp' => $dayActivities->sum('xp_earned'),
                'minutes' => round($dayActivities->sum('session_duration_seconds') / 60),
            ];
        }

        // Distribución por tipo de actividad (Typing vs English Games)
        $activityTypes = collect($recentActivities)->groupBy('activity_type')->map->count();

        // Estadísticas específicas de TypeMaster
        $typingStats = $this->getTypingStats($user->id, $startOfWeek);

        // Estadísticas específicas de English Games
        $englishStats = $this->getEnglishGamesStats($user->id, $startOfWeek);

        // Comparación de rendimiento entre juegos
        $gameComparison = [
            'typing' => [
                'sessions' => $typingStats['total_sessions'],
                'xp' => $typingStats['total_xp'],
                'time' => $typingStats['total_minutes'],
            ],
            'english' => [
                'sessions' => $englishStats['total_sessions'],
                'xp' => $englishStats['total_xp'],
                'time' => $englishStats['total_minutes'],
            ],
        ];

        // Resumen de XP y progreso de nivel
        $todayXp = UserActivityTracker::where('user_id', $user->id)
            ->whereDate('session_start', \Carbon\Carbon::today())
            ->sum('xp_earned');

        $weeklyXp = $weekActivities->sum('xp_earned');
        $totalXp = max(0, (int) ($progress->total_xp ?? 0));
        $level = max(1, (int) ($progress->level ?? 1));

        $xpForPreviousLevel = $level > 1
            ? $progress->calculateXPForNextLevel($level - 1)
            : 0;
        $xpForNextLevel = $progress->calculateXPForNextLevel($level);

        $currentLevelXp = max(0, $totalXp - $xpForPreviousLevel);
        $xpNeededForLevel = max(0, $xpForNextLevel - $xpForPreviousLevel);
        $xpToNextLevel = max(0, $xpForNextLevel - $totalXp);

        $levelProgressPercentage = $xpNeededForLevel > 0
            ? round(($currentLevelXp / $xpNeededForLevel) * 100)
            : 100;

        $xpSummary = [
            'today_xp' => $todayXp,
            'weekly_xp' => $weeklyXp,
            'total_xp' => $totalXp,
            'level' => $level,
            'xp_to_next_level' => $xpToNextLevel,
            'level_progress_percent' => min(100, max(0, $levelProgressPercentage)),
            'current_level_xp' => $currentLevelXp,
            'required_level_xp' => $xpNeededForLevel,
        ];

        // --- Leaderboard Logic ---
        // Top 3 usuarios con más XP esta semana
        $leaderboard = UserActivityTracker::select('user_id', DB::raw('SUM(xp_earned) as total_xp'))
            ->where('session_start', '>=', $startOfWeek)
            ->groupBy('user_id')
            ->orderByDesc('total_xp')
            ->limit(3)
            ->with('user:id,name,avatar')
            ->get();

        // Posición del usuario actual
        $userRank = UserActivityTracker::select('user_id', DB::raw('SUM(xp_earned) as total_xp'))
            ->where('session_start', '>=', $startOfWeek)
            ->groupBy('user_id')
            ->having('total_xp', '>', $weeklyXp)
            ->get()
            ->count() + 1;

        // Datos para gráfico comparativo (Top 1 vs Usuario)
        $topUserXp = $leaderboard->first()->total_xp ?? 0;
        $comparisonData = [
            'user_xp' => $weeklyXp,
            'top_xp' => $topUserXp,
            'gap' => max(0, $topUserXp - $weeklyXp),
        ];

        // Usar la nueva vista 'games.dashboard' en lugar de 'engagement.dashboard'
        return view('games.dashboard', [
            'user' => $user,
            'progress' => $progress,
            'recentActivities' => $recentActivities,
            'weekActivities' => $weekActivities,
            'qualityDistribution' => $qualityDistribution,
            'dailyProgress' => $dailyProgress,
            'activityTypes' => $activityTypes,
            'typingStats' => $typingStats,
            'englishStats' => $englishStats,
            'gameComparison' => $gameComparison,
            'xpSummary' => $xpSummary,
            'aiSummary' => $aiSummary,
            'leaderboard' => $leaderboard,
            'userRank' => $userRank,
            'comparisonData' => $comparisonData,
            'pageTitle' => 'Dashboard de Juegos',
        ]);
    }

    /**
     * Forzar actualización del resumen de IA
     */
    public function refreshSummary()
    {
        $user = Auth::user();
        $cacheKey = 'gamification_summary_'.$user->id;

        // Borrar caché
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        // Redirigir al dashboard (que regenerará el resumen)
        // O mejor, regenerarlo aquí y devolver JSON si es AJAX

        $progress = $user->gameProgress;
        $context = [
            'name' => $user->name,
            'level' => $progress->level,
            'total_xp' => $progress->total_xp,
            'days_offline' => $user->last_login_at ? now()->diffInDays($user->last_login_at) : 0,
            'recent_activity' => $this->getRecentActivitySummary($user->id),
            'age' => $user->age,
        ];

        $aiController = new \App\Http\Controllers\AIController;
        $request = new Request(['context' => $context]);
        $response = $aiController->generateGamificationSummary($request);
        $data = json_decode($response->getContent(), true);

        if ($data['success']) {
            $aiSummary = [
                'message' => $data['message'],
                'audioUrl' => $data['audioUrl'],
                'generated_at' => now()->toIso8601String(),
            ];
            \Illuminate\Support\Facades\Cache::put($cacheKey, $aiSummary, 3600);

            return response()->json([
                'success' => true,
                'summary' => $aiSummary,
            ]);
        }

        return response()->json(['success' => false, 'error' => 'No se pudo generar el resumen'], 500);
    }

    /**
     * Helper para obtener resumen de actividad reciente en texto
     */
    private function getRecentActivitySummary($userId)
    {
        $lastActivity = UserActivityTracker::where('user_id', $userId)
            ->orderBy('session_start', 'desc')
            ->first();

        if (! $lastActivity) {
            return 'Sin actividad reciente';
        }

        $type = $lastActivity->activity_type == 'typing' ? 'Mecanografía' : 'Juegos de Inglés';
        $date = \Carbon\Carbon::parse($lastActivity->session_start)->diffForHumans();

        return "Jugó $type $date ganando {$lastActivity->xp_earned} XP";
    }

    /**
     * Obtener estadísticas de TypeMaster
     */
    private function getTypingStats($userId, $startOfWeek)
    {
        $activities = UserActivityTracker::where('user_id', $userId)
            ->where('activity_type', 'typing')
            ->where('session_start', '>=', $startOfWeek)
            ->get();

        return [
            'total_sessions' => $activities->count(),
            'total_xp' => $activities->sum('xp_earned'),
            'total_minutes' => round($activities->sum('session_duration_seconds') / 60),
            'best_session_xp' => $activities->max('xp_earned'),
        ];
    }

    /**
     * Obtener estadísticas de English Games
     */
    private function getEnglishGamesStats($userId, $startOfWeek)
    {
        // Obtener sesiones de juegos de inglés
        $gameSessions = DB::table('english_game_sessions')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $startOfWeek)
            ->get();

        // Calcular XP total basado en las sesiones
        $totalXp = $gameSessions->sum(function ($session) {
            return $this->calculateGameXP($session);
        });

        return [
            'total_sessions' => $gameSessions->count(),
            'total_xp' => $totalXp,
            'total_minutes' => round($gameSessions->sum('time_spent') / 60),
            'avg_accuracy' => $gameSessions->avg('accuracy'),
            'best_score' => $gameSessions->max('score'),
            'games_played' => $gameSessions->groupBy('game_type')->map->count(),
        ];
    }

    /**
     * Calcular XP de una sesión de juego (misma fórmula que en EnglishGamesController)
     */
    private function calculateGameXP($session)
    {
        $baseXP = 50;
        $accuracyBonus = ($session->accuracy / 100) * 50;
        $speedBonus = max(0, (120 - $session->time_spent) / 2);

        return floor(($baseXP + $accuracyBonus + $speedBonus) * $session->level);
    }

    private function mapQualityFromDuration(int $durationSeconds): string
    {
        if ($durationSeconds < 300) {
            return 'micro';
        }
        if ($durationSeconds < 900) {
            return 'productive';
        }

        return 'excellent';
    }

    /**
     * API para obtener estadísticas rápidas (para widgets)
     */
    public function quickStats()
    {
        $user = Auth::user();
        $progress = $user->gameProgress;

        return response()->json([
            'level' => $progress->level ?? 1,
            'total_xp' => $progress->total_xp ?? 0,
            'current_streak' => $progress->current_streak ?? 0,
            'typing_score' => $progress->typing_score ?? 0,
            'tutor_score' => $progress->tutor_score ?? 0,
            'sessions_today' => UserActivityTracker::where('user_id', $user->id)
                ->whereDate('session_start', today())
                ->count(),
        ]);
    }
}
