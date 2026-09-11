<?php

namespace App\Http\Controllers;

use App\Models\ExerciseResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExerciseResultController extends Controller
{
    /**
     * Muestra el historial de resultados de ejercicios del usuario
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $type = $request->query('type'); // Filtrar por tipo (opcional)
        $level = $request->query('level'); // Filtrar por nivel (opcional)

        $query = ExerciseResult::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Aplicar filtros si se proporcionan
        if ($type) {
            $query->where('type', $type);
        }

        if ($level) {
            $query->where('level', $level);
        }

        $results = $query->paginate(10);

        return view('tutor.exercise_history', [
            'results' => $results,
            'type' => $type,
            'level' => $level,
        ]);
    }

    /**
     * Muestra un resultado específico de ejercicio
     */
    public function show($id)
    {
        $result = ExerciseResult::findOrFail($id);

        // Verificar que el resultado pertenezca al usuario actual
        if ($result->user_id !== Auth::id()) {
            abort(403, 'No tienes permiso para ver este resultado');
        }

        return view('tutor.exercise_result', [
            'result' => $result,
        ]);
    }

    /**
     * Elimina un resultado de ejercicio
     */
    public function destroy($id)
    {
        $result = ExerciseResult::findOrFail($id);

        // Verificar que el resultado pertenezca al usuario actual
        if ($result->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar este resultado',
            ], 403);
        }

        $result->delete();

        return response()->json([
            'success' => true,
            'message' => 'Resultado eliminado correctamente',
        ]);
    }

    /**
     * Devuelve estadísticas de los ejercicios del usuario
     */
    public function getStats()
    {
        $user = Auth::user();

        // Obtener estadísticas por tipo de ejercicio
        $stats = [
            'vocabulary' => [
                'count' => ExerciseResult::where('user_id', $user->id)->where('type', 'vocabulary')->count(),
                'avg_score' => ExerciseResult::where('user_id', $user->id)->where('type', 'vocabulary')->avg('score') ?? 0,
            ],
            'grammar' => [
                'count' => ExerciseResult::where('user_id', $user->id)->where('type', 'grammar')->count(),
                'avg_score' => ExerciseResult::where('user_id', $user->id)->where('type', 'grammar')->avg('score') ?? 0,
            ],
            'speaking' => [
                'count' => ExerciseResult::where('user_id', $user->id)->where('type', 'speaking')->count(),
                'avg_score' => ExerciseResult::where('user_id', $user->id)->where('type', 'speaking')->avg('score') ?? 0,
            ],
            'listening' => [
                'count' => ExerciseResult::where('user_id', $user->id)->where('type', 'listening')->count(),
                'avg_score' => ExerciseResult::where('user_id', $user->id)->where('type', 'listening')->avg('score') ?? 0,
            ],
        ];

        // Obtener progreso general
        $totalExercises = ExerciseResult::where('user_id', $user->id)->count();
        $avgScore = ExerciseResult::where('user_id', $user->id)->avg('score') ?? 0;

        // Obtener los últimos 5 ejercicios
        $recentResults = ExerciseResult::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'total_exercises' => $totalExercises,
            'avg_score' => round($avgScore, 1),
            'recent_results' => $recentResults,
        ]);
    }

    /**
     * Muestra las estadísticas de ejercicios del usuario en una vista
     */
    public function statistics(Request $request)
    {
        $user = Auth::user();
        $period = $request->query('period', 'month'); // Periodo por defecto: mes

        // Determinar la fecha de inicio según el periodo
        $startDate = now();
        switch ($period) {
            case 'week':
                $startDate = $startDate->subWeek();
                break;
            case 'month':
                $startDate = $startDate->subMonth();
                break;
            case 'year':
                $startDate = $startDate->subYear();
                break;
            case 'all':
                $startDate = null;
                break;
        }

        // Consulta base
        $query = ExerciseResult::where('user_id', $user->id);

        // Aplicar filtro de fecha si no es "all"
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        // Estadísticas generales
        $totalExercises = $query->count();
        $averageScore = round($query->avg('score') ?? 0);
        $bestScore = round($query->max('score') ?? 0);

        // Calcular racha de días consecutivos
        $streak = $this->calculateStreak($user->id);

        // Distribución por tipo de ejercicio
        $typeDistribution = [
            'vocabulary' => $query->where('type', 'vocabulary')->count(),
            'grammar' => $query->where('type', 'grammar')->count(),
            'speaking' => $query->where('type', 'speaking')->count(),
            'listening' => $query->where('type', 'listening')->count(),
        ];

        // Puntuación media por tipo
        $scoreByType = [
            'vocabulary' => round($query->where('type', 'vocabulary')->avg('score') ?? 0),
            'grammar' => round($query->where('type', 'grammar')->avg('score') ?? 0),
            'speaking' => round($query->where('type', 'speaking')->avg('score') ?? 0),
            'listening' => round($query->where('type', 'listening')->avg('score') ?? 0),
        ];

        // Datos para el gráfico de progreso
        $progressResults = ExerciseResult::where('user_id', $user->id)
            ->when($startDate, function ($q) use ($startDate) {
                return $q->where('created_at', '>=', $startDate);
            })
            ->orderBy('created_at')
            ->get(['created_at', 'score', 'type']);

        $progressData = [
            'labels' => $progressResults->map(function ($result) {
                return $result->created_at->format('d/m/Y');
            }),
            'scores' => $progressResults->pluck('score'),
        ];

        // Estadísticas por nivel
        $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        $levelStats = [];

        foreach ($levels as $level) {
            $levelQuery = clone $query;
            $levelResults = $levelQuery->where('level', $level);

            $levelStats[$level] = [
                'count' => $levelResults->count(),
                'average' => round($levelResults->avg('score') ?? 0),
                'best' => round($levelResults->max('score') ?? 0),
            ];
        }

        // Preparar datos para la vista
        $stats = [
            'total_exercises' => $totalExercises,
            'average_score' => $averageScore,
            'best_score' => $bestScore,
            'streak' => $streak,
            'type_distribution' => $typeDistribution,
            'score_by_type' => $scoreByType,
            'progress_data' => $progressData,
            'levels' => $levelStats,
        ];

        return view('tutor.exercise_statistics', [
            'stats' => $stats,
            'period' => $period,
        ]);
    }

    /**
     * Calcula la racha de días consecutivos con ejercicios completados
     */
    private function calculateStreak($userId)
    {
        $streak = 0;
        $today = now()->startOfDay();
        $checkDate = clone $today;

        // Verificar si hay ejercicios hoy
        $hasExerciseToday = ExerciseResult::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->exists();

        // Si no hay ejercicios hoy, empezar a contar desde ayer
        if (! $hasExerciseToday) {
            $checkDate = $checkDate->subDay();
        }

        // Contar días consecutivos hacia atrás
        $continuousStreak = true;
        while ($continuousStreak) {
            $hasExercise = ExerciseResult::where('user_id', $userId)
                ->whereDate('created_at', $checkDate)
                ->exists();

            if ($hasExercise) {
                $streak++;
                $checkDate = $checkDate->subDay();
            } else {
                $continuousStreak = false;
            }
        }

        return $streak;
    }
}
