<?php

namespace App\Http\Controllers;

use App\Models\CourseProgress;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserGameProgress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // Métricas generales
        $totalUsers = User::count();
        $activeUsersToday = $this->getActiveUsersCount('today');
        $activeUsersWeek = $this->getActiveUsersCount('week');
        $activeUsersMonth = $this->getActiveUsersCount('month');

        // Métricas de gamificación
        $totalXP = UserGameProgress::sum('total_xp');
        $avgXP = UserGameProgress::avg('total_xp');
        $avgLevel = UserGameProgress::avg('level');
        $avgStreak = UserGameProgress::avg('current_streak');
        $maxStreak = UserGameProgress::max('current_streak');

        // Métricas de logros
        $totalAchievements = DB::table('achievements')->count();
        $unlockedAchievements = DB::table('user_achievements')->count();
        $avgAchievementsPerUser = $totalUsers > 0 ? $unlockedAchievements / $totalUsers : 0;

        // Métricas de cursos
        $totalCourses = DB::table('courses')->count();
        $completedSessions = CourseProgress::where('completed', true)->count();
        $totalSessions = CourseProgress::count();
        $completionRate = $totalSessions > 0 ? ($completedSessions / $totalSessions) * 100 : 0;

        // Métricas de notificaciones
        $totalNotifications = Notification::count();
        $readNotifications = Notification::where('is_read', true)->count();
        $notificationReadRate = $totalNotifications > 0 ? ($readNotifications / $totalNotifications) * 100 : 0;

        // Top usuarios por XP
        $topUsersByXP = UserGameProgress::with('user')
            ->orderBy('total_xp', 'desc')
            ->take(10)
            ->get();

        // Top usuarios por racha
        $topUsersByStreak = UserGameProgress::with('user')
            ->orderBy('current_streak', 'desc')
            ->take(10)
            ->get();

        // Distribución de niveles
        $levelDistribution = UserGameProgress::select('level', DB::raw('count(*) as count'))
            ->groupBy('level')
            ->orderBy('level')
            ->get();

        // Actividad por día de la semana (últimas 4 semanas)
        $activityByDay = $this->getActivityByDayOfWeek();

        // Evolución de usuarios activos (últimos 30 días)
        $activeUsersEvolution = $this->getActiveUsersEvolution();

        // Usuarios con más logros
        $topUsersByAchievements = User::withCount('achievements')
            ->orderBy('achievements_count', 'desc')
            ->take(10)
            ->get();

        // Tabla completa de usuarios con paginación
        $users = User::with(['gameProgress', 'courseProgress', 'achievements'])
            ->paginate(20);

        return view('admin.dashboard', compact(
            'totalUsers',
            'activeUsersToday',
            'activeUsersWeek',
            'activeUsersMonth',
            'totalXP',
            'avgXP',
            'avgLevel',
            'avgStreak',
            'maxStreak',
            'totalAchievements',
            'unlockedAchievements',
            'avgAchievementsPerUser',
            'totalCourses',
            'completedSessions',
            'totalSessions',
            'completionRate',
            'totalNotifications',
            'readNotifications',
            'notificationReadRate',
            'topUsersByXP',
            'topUsersByStreak',
            'levelDistribution',
            'activityByDay',
            'activeUsersEvolution',
            'topUsersByAchievements',
            'users'
        ));
    }

    private function getActiveUsersCount($period)
    {
        $date = match ($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->subWeek(),
            'month' => Carbon::now()->subMonth(),
            default => Carbon::today()
        };

        return UserGameProgress::where('last_activity_at', '>=', $date)->count();
    }

    private function getActivityByDayOfWeek()
    {
        $fourWeeksAgo = Carbon::now()->subWeeks(4);

        $activity = UserGameProgress::where('last_activity_at', '>=', $fourWeeksAgo)
            ->select(
                DB::raw('DAYOFWEEK(last_activity_at) as day_of_week'),
                DB::raw('COUNT(DISTINCT user_id) as users_count')
            )
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get();

        // Convertir números de día a nombres
        $daysMap = [
            1 => 'Domingo',
            2 => 'Lunes',
            3 => 'Martes',
            4 => 'Miércoles',
            5 => 'Jueves',
            6 => 'Viernes',
            7 => 'Sábado',
        ];

        return $activity->map(function ($item) use ($daysMap) {
            return [
                // @phpstan-ignore-next-line
                'day' => $daysMap[$item->day_of_week] ?? 'Unknown',
                // @phpstan-ignore-next-line
                'count' => $item->users_count,
            ];
        });
    }

    private function getActiveUsersEvolution()
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $evolution = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $count = UserGameProgress::whereDate('last_activity_at', $date)->distinct('user_id')->count();
            $evolution[] = [
                'date' => $date,
                'count' => $count,
            ];
        }

        return collect($evolution);
    }

    public function getUsersData(Request $request)
    {
        $query = User::with(['gameProgress', 'courseProgress', 'achievements']);

        // Filtros
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('level')) {
            $query->whereHas('gameProgress', function ($q) use ($request) {
                $q->where('level', $request->level);
            });
        }

        if ($request->has('min_xp')) {
            $query->whereHas('gameProgress', function ($q) use ($request) {
                $q->where('total_xp', '>=', $request->min_xp);
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'total_xp');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['total_xp', 'level', 'current_streak'])) {
            $query->join('user_game_progress', 'users.id', '=', 'user_game_progress.user_id')
                ->orderBy("user_game_progress.{$sortBy}", $sortOrder)
                ->select('users.*');
        }

        $users = $query->paginate(20);

        return response()->json($users);
    }

    public function exportMetrics()
    {
        // Generar CSV con todas las métricas
        $users = User::with(['gameProgress', 'courseProgress', 'achievements'])->get();

        $csvData = "Nombre,Email,Nivel,XP Total,Racha Actual,Logros,Sesiones Completadas,Última Actividad\n";

        foreach ($users as $user) {
            $gameProgress = $user->gameProgress;
            $achievementsCount = $user->achievements->count();
            $completedSessions = $user->courseProgress()
                ->where('completed', true)
                ->count();

            $csvData .= sprintf(
                '"%s","%s",%d,%d,%d,%d,%d,"%s"'."\n",
                $user->name,
                $user->email,
                $gameProgress->level ?? 0,
                $gameProgress->total_xp ?? 0,
                $gameProgress->current_streak ?? 0,
                $achievementsCount,
                $completedSessions,
                $gameProgress->last_activity_at ?? 'N/A'
            );
        }

        return response($csvData)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="metrics_'.date('Y-m-d').'.csv"');
    }

    /**
     * Vista detallada de analytics de un usuario
     */
    public function userAnalytics($userId)
    {
        $user = User::with(['gameProgress', 'achievements'])->findOrFail($userId);
        $progress = $user->gameProgress;

        // TypeMaster Analytics
        $typingStats = $this->getTypingAnalytics($userId);

        // English Games Analytics
        $englishStats = $this->getEnglishGamesAnalytics($userId);

        // Engagement Analytics
        $engagementStats = $this->getEngagementAnalytics($userId);

        // Activity Calendar (últimos 90 días)
        $activityCalendar = $this->getActivityCalendar($userId);

        return view('admin.user-analytics', compact(
            'user',
            'progress',
            'typingStats',
            'englishStats',
            'engagementStats',
            'activityCalendar'
        ));
    }

    /**
     * Obtener analytics de TypeMaster
     */
    private function getTypingAnalytics($userId)
    {
        $sessions = DB::table('typing_sessions')
            ->where('user_id', $userId)
            ->where('completed', true)
            ->orderBy('session_date', 'asc')
            ->get();

        if ($sessions->isEmpty()) {
            return [
                'total_sessions' => 0,
                'total_time' => 0,
                'avg_wpm' => 0,
                'best_wpm' => 0,
                'avg_accuracy' => 0,
                'best_accuracy' => 0,
                'wpm_evolution' => [],
                'accuracy_evolution' => [],
                'by_mode' => [],
                'by_game_mode' => [],
                'error_analysis' => [],
            ];
        }

        // Evolución WPM y precisión en el tiempo
        $wpmEvolution = [];
        $accuracyEvolution = [];
        foreach ($sessions as $session) {
            $date = Carbon::parse($session->session_date)->format('Y-m-d');
            $wpmEvolution[] = [
                'date' => $date,
                'wpm' => $session->wpm,
            ];
            $accuracyEvolution[] = [
                'date' => $date,
                'accuracy' => (float) $session->accuracy,
            ];
        }

        // Estadísticas por modo
        $byMode = DB::table('typing_sessions')
            ->where('user_id', $userId)
            ->where('completed', true)
            ->select(
                'mode',
                DB::raw('COUNT(*) as sessions'),
                DB::raw('AVG(wpm) as avg_wpm'),
                DB::raw('MAX(wpm) as best_wpm'),
                DB::raw('AVG(accuracy) as avg_accuracy'),
                DB::raw('SUM(time_seconds) as total_time')
            )
            ->groupBy('mode')
            ->get();

        // Estadísticas por modo de juego (arcade, survival, etc.)
        $byGameMode = DB::table('typing_sessions')
            ->where('user_id', $userId)
            ->where('completed', true)
            ->whereNotNull('game_mode')
            ->select(
                'game_mode',
                DB::raw('COUNT(*) as sessions'),
                DB::raw('MAX(mode_score) as best_score'),
                DB::raw('AVG(mode_score) as avg_score'),
                DB::raw('AVG(wpm) as avg_wpm'),
                DB::raw('AVG(accuracy) as avg_accuracy')
            )
            ->groupBy('game_mode')
            ->get();

        // Análisis de errores (teclas problemáticas)
        $errorAnalysis = [];
        $errorCounts = [];
        foreach ($sessions as $session) {
            if ($session->error_analysis) {
                $errors = json_decode($session->error_analysis, true);
                if (is_array($errors)) {
                    foreach ($errors as $key => $count) {
                        if (! isset($errorCounts[$key])) {
                            $errorCounts[$key] = 0;
                        }
                        $errorCounts[$key] += $count;
                    }
                }
            }
        }
        arsort($errorCounts);
        $errorAnalysis = array_slice($errorCounts, 0, 10, true);

        return [
            'total_sessions' => $sessions->count(),
            'total_time' => round($sessions->sum('time_seconds') / 60, 1), // en minutos
            'avg_wpm' => round($sessions->avg('wpm'), 1),
            'best_wpm' => $sessions->max('wpm'),
            'avg_accuracy' => round($sessions->avg('accuracy'), 1),
            'best_accuracy' => round($sessions->max('accuracy'), 1),
            'wpm_evolution' => $wpmEvolution,
            'accuracy_evolution' => $accuracyEvolution,
            'by_mode' => $byMode,
            'by_game_mode' => $byGameMode,
            'error_analysis' => $errorAnalysis,
        ];
    }

    /**
     * Obtener analytics de juegos de inglés
     */
    private function getEnglishGamesAnalytics($userId)
    {
        $sessions = DB::table('english_game_sessions')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($sessions->isEmpty()) {
            return [
                'total_sessions' => 0,
                'total_time' => 0,
                'avg_score' => 0,
                'avg_accuracy' => 0,
                'by_game_type' => [],
                'score_evolution' => [],
                'accuracy_by_game' => [],
            ];
        }

        // Evolución de scores en el tiempo
        $scoreEvolution = [];
        foreach ($sessions as $session) {
            $date = Carbon::parse($session->created_at)->format('Y-m-d');
            $scoreEvolution[] = [
                'date' => $date,
                'score' => $session->score,
                'game_type' => $session->game_type,
            ];
        }

        // Estadísticas por tipo de juego
        $byGameType = DB::table('english_game_sessions')
            ->where('user_id', $userId)
            ->select(
                'game_type',
                DB::raw('COUNT(*) as sessions'),
                DB::raw('AVG(score) as avg_score'),
                DB::raw('MAX(score) as best_score'),
                DB::raw('AVG(accuracy) as avg_accuracy'),
                DB::raw('SUM(time_spent) as total_time'),
                DB::raw('SUM(correct_answers) as total_correct'),
                DB::raw('SUM(total_questions) as total_questions')
            )
            ->groupBy('game_type')
            ->get();

        // Precisión promedio por juego
        $accuracyByGame = [];
        foreach ($byGameType as $game) {
            $accuracyByGame[$game->game_type] = round($game->avg_accuracy, 1);
        }

        return [
            'total_sessions' => $sessions->count(),
            'total_time' => round($sessions->sum('time_spent') / 60, 1), // en minutos
            'avg_score' => round($sessions->avg('score'), 1),
            'avg_accuracy' => round($sessions->avg('accuracy'), 1),
            'by_game_type' => $byGameType,
            'score_evolution' => $scoreEvolution,
            'accuracy_by_game' => $accuracyByGame,
        ];
    }

    /**
     * Obtener analytics de engagement
     */
    private function getEngagementAnalytics($userId)
    {
        $progress = UserGameProgress::where('user_id', $userId)->first();

        // Actividad por día de la semana
        $activityByDay = DB::table('typing_sessions')
            ->where('user_id', $userId)
            ->select(
                DB::raw('DAYOFWEEK(session_date) as day_of_week'),
                DB::raw('COUNT(*) as sessions_count')
            )
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get();

        $daysMap = [
            1 => 'Domingo',
            2 => 'Lunes',
            3 => 'Martes',
            4 => 'Miércoles',
            5 => 'Jueves',
            6 => 'Viernes',
            7 => 'Sábado',
        ];

        $activityByDayFormatted = [];
        foreach ($activityByDay as $day) {
            $activityByDayFormatted[] = [
                'day' => $daysMap[$day->day_of_week] ?? 'Unknown',
                'count' => $day->sessions_count,
            ];
        }

        // Actividad por hora del día
        $activityByHour = DB::table('typing_sessions')
            ->where('user_id', $userId)
            ->select(
                DB::raw('HOUR(session_date) as hour'),
                DB::raw('COUNT(*) as sessions_count')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return [
            'current_streak' => $progress->current_streak ?? 0,
            'longest_streak' => $progress->longest_streak ?? 0,
            'total_login_days' => $progress->total_login_days ?? 0,
            'days_inactive' => $progress->days_inactive ?? 0,
            'engagement_score' => $progress->engagement_score ?? 0,
            'activity_by_day' => $activityByDayFormatted,
            'activity_by_hour' => $activityByHour,
            'sessions_this_week' => $progress->sessions_this_week ?? 0,
        ];
    }

    /**
     * Obtener calendario de actividad (últimos 90 días)
     */
    private function getActivityCalendar($userId)
    {
        $startDate = Carbon::now()->subDays(89);
        $endDate = Carbon::now();

        // Obtener todas las sesiones (typing + english)
        $typingSessions = DB::table('typing_sessions')
            ->where('user_id', $userId)
            ->where('session_date', '>=', $startDate)
            ->select(DB::raw('DATE(session_date) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $englishSessions = DB::table('english_game_sessions')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        // Crear array con todos los días
        $calendar = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $typingCount = $typingSessions[$dateStr] ?? 0;
            $englishCount = $englishSessions[$dateStr] ?? 0;
            $totalCount = $typingCount + $englishCount;

            $calendar[] = [
                'date' => $dateStr,
                'count' => $totalCount,
                'level' => $this->getActivityLevel($totalCount),
            ];

            $currentDate->addDay();
        }

        return $calendar;
    }

    /**
     * Determinar nivel de actividad para el calendario
     */
    private function getActivityLevel($count)
    {
        if ($count == 0) {
            return 0;
        }
        if ($count <= 2) {
            return 1;
        }
        if ($count <= 5) {
            return 2;
        }
        if ($count <= 10) {
            return 3;
        }

        return 4;
    }
}
