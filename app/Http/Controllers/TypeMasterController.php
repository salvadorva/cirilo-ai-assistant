<?php

namespace App\Http\Controllers;

use App\Http\Traits\TracksUserActivity;
use App\Models\Notification;
use App\Models\TypingLesson;
use App\Models\TypingSession;
use App\Models\UserGameProgress;
use App\Models\UserLessonProgress;
use App\Services\AchievementService;
use App\Services\StreakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TypeMasterController extends Controller
{
    use TracksUserActivity;

    /**
     * Mostrar la página principal de TypeMaster
     */
    public function index()
    {
        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        return view('typing.index', [
            'user' => $user,
            'gameProgress' => $gameProgress,
            'pageTitle' => 'TypeMaster AI - Aprende Mecanografía',
        ]);
    }

    /**
     * Mostrar la interfaz de juego
     */
    public function play(Request $request)
    {
        $level = $request->get('level', 'beginner');
        $mode = $request->get('mode', 'practice');

        return view('typing.play', [
            'level' => $level,
            'mode' => $mode,
            'pageTitle' => 'TypeMaster AI - Practicando',
        ]);
    }

    /**
     * Generar texto de práctica con IA
     */
    public function generateText(Request $request)
    {
        $request->validate([
            'level' => 'sometimes|in:beginner,intermediate,advanced',
            'theme' => 'sometimes|in:general,tech,literature,science,business',
            'length' => 'sometimes|in:short,medium,long',
        ]);
        $level = $request->input('level', 'beginner');
        $theme = $request->input('theme', 'general');
        $length = $request->input('length', 'short');

        // Configurar prompts según el nivel
        $prompts = [
            'beginner' => 'Genera un texto simple de práctica de mecanografía para principiantes. Usa palabras comunes y frases cortas. Máximo 3 líneas.',
            'intermediate' => 'Genera un texto de práctica de mecanografía de nivel intermedio. Incluye signos de puntuación básicos y palabras de dificultad media. Máximo 5 líneas.',
            'advanced' => 'Genera un texto avanzado de práctica de mecanografía. Incluye números, símbolos especiales, y vocabulario técnico. Máximo 7 líneas.',
        ];

        $lengthModifiers = [
            'short' => 'Texto corto de 2-3 líneas.',
            'medium' => 'Texto mediano de 4-6 líneas.',
            'long' => 'Texto largo de 7-10 líneas.',
        ];

        $themeModifiers = [
            'general' => 'sobre temas generales',
            'tech' => 'sobre tecnología y programación',
            'literature' => 'literario o de cuentos',
            'science' => 'sobre ciencia y naturaleza',
            'business' => 'sobre negocios y empresas',
        ];

        $prompt = $prompts[$level].' '.$lengthModifiers[$length].' El tema debe ser '.$themeModifiers[$theme].'.';

        try {
            // Usar el mismo sistema de IA que el resto de la aplicación
            $aiController = new \App\Http\Controllers\AIController;
            $aiRequest = new Request(['prompt' => $prompt, 'generateAudio' => false]);
            $aiRequest->setUserResolver(fn () => $request->user());
            $response = $aiController->generateText($aiRequest);
            $data = json_decode($response->getContent(), true);

            if ($response->isSuccessful() && isset($data['choices'][0]['message']['content'])) {
                return response()->json([
                    'success' => true,
                    'text' => $data['choices'][0]['message']['content'],
                    'level' => $level,
                    'theme' => $theme,
                    'length' => $length,
                ]);
            } else {
                throw new \Exception('Error en la generación de texto');
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'No se pudo generar el texto. Puedes usar el texto de práctica alternativo.',
                'fallback_text' => $this->getFallbackText($level),
            ]);
        }
    }

    /**
     * Guardar resultado de sesión de typing
     */
    public function saveSession(Request $request)
    {
        $request->validate([
            'wpm' => 'required|numeric|min:0',
            'accuracy' => 'required|numeric|min:0|max:100',
            'errors' => 'required|integer|min:0',
            'time_seconds' => 'required|integer|min:1',
            'text_used' => 'required|string',
            'level' => 'required|string',
        ]);

        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        // Guardar el nivel anterior para detectar level up
        $previousLevel = $gameProgress->level;

        // Calcular XP basado en rendimiento
        $xpEarned = $this->calculateXPFromSession($request->all());

        // Actualizar progreso del juego
        $gameProgress->addXP($xpEarned);

        // Detectar si subió de nivel
        $levelUp = $gameProgress->level > $previousLevel;

        // Crear notificación de progreso
        if ($xpEarned > 0) {
            $message = "Has ganado {$xpEarned} XP. WPM: {$request->wpm}, Precisión: {$request->accuracy}%";
            if ($levelUp) {
                $message .= " ¡Has subido al nivel {$gameProgress->level}!";
            }

            Notification::create([
                'user_id' => $user->id,
                'title' => $levelUp ? '¡Nivel Alcanzado!' : '¡Sesión de Typing Completada!',
                'message' => $message,
                'type' => $levelUp ? 'level_up' : 'typing_session',
                'icon' => $levelUp ? 'fas fa-star' : 'fas fa-keyboard',
                'color' => $levelUp ? '#ffd700' : '#28a745',
            ]);
        }

        // Guardar sesión en la base de datos
        $typingSession = TypingSession::create([
            'user_id' => $user->id,
            'mode' => $request->input('mode', 'practice'),
            'level' => $request->level,
            'theme' => $request->input('theme'),
            'text_used' => $request->text_used,
            'wpm' => $request->wpm,
            'accuracy' => $request->accuracy,
            'errors' => $request->errors,
            'time_seconds' => $request->time_seconds,
            'total_keystrokes' => strlen($request->text_used), // Aproximación
            'correct_keystrokes' => round(strlen($request->text_used) * ($request->accuracy / 100)),
            'xp_earned' => $xpEarned,
            'completed' => true,
            'session_date' => now(),
        ]);

        // Trackear actividad para sistema de engagement
        $this->trackActivity(
            $user->id,
            'typing',
            $request->time_seconds,
            $xpEarned,
            $this->calculateSessionQuality($request->time_seconds, $xpEarned)
        );

        // Actualizar racha del usuario
        $streakService = app(StreakService::class);
        $streakInfo = $streakService->updateStreak($user->id);

        // Verificar y otorgar logros automáticamente
        $achievementService = app(AchievementService::class);
        $newAchievements = $achievementService->checkAndGrantAchievements($user);

        return response()->json([
            'success' => true,
            'xp_earned' => $xpEarned,
            'new_level' => $gameProgress->level,
            'total_xp' => $gameProgress->total_xp,
            'level_up' => $levelUp,
            'current_level_xp' => $gameProgress->current_level_xp,
            'xp_to_next_level' => $gameProgress->xp_to_next_level,
            'streak' => [
                'current' => $streakInfo['current_streak'],
                'longest' => $streakInfo['longest_streak'],
                'status' => $streakInfo['streak_status'] ?? 'maintained',
            ],
            'new_achievements' => $newAchievements,
            'achievements_count' => count($newAchievements),
            'message' => $levelUp ? "¡Felicidades! Has alcanzado el nivel {$gameProgress->level}" : "¡Excelente! Has ganado {$xpEarned} XP.",
        ]);
    }

    /**
     * Obtener estadísticas del usuario
     */
    public function getStats()
    {
        $user = Auth::user();

        // Obtener estadísticas reales de las sesiones
        $stats = TypingSession::getUserStats($user->id, 30);

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Mostrar la página de estadísticas del usuario
     */
    public function showStats(Request $request)
    {
        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        // Obtener estadísticas detalladas
        $stats = TypingSession::getUserStats($user->id, 30);

        // Si es una petición AJAX, devolver JSON para compatibilidad
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        }

        // Obtener sesiones recientes
        $recentSessions = TypingSession::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Obtener estadísticas por modo de juego
        $modeStats = TypingSession::where('user_id', $user->id)
            ->selectRaw('mode, COUNT(*) as sessions, AVG(wpm) as avg_wpm, AVG(accuracy) as avg_accuracy, SUM(xp_earned) as total_xp')
            ->groupBy('mode')
            ->get();

        return view('typing.stats', [
            'user' => $user,
            'gameProgress' => $gameProgress,
            'stats' => $stats,
            'recentSessions' => $recentSessions,
            'modeStats' => $modeStats,
            'pageTitle' => 'Estadísticas - TypeMaster AI',
        ]);
    }

    /**
     * Calcular XP ganado en una sesión
     */
    private function calculateXPFromSession($sessionData)
    {
        $wpm = $sessionData['wpm'];
        $accuracy = $sessionData['accuracy'];
        $timeSeconds = $sessionData['time_seconds'];

        // Base XP por completar sesión
        $baseXP = 10;

        // Bonus por WPM (1 XP por cada 5 WPM)
        $wpmBonus = floor($wpm / 5) * 2;

        // Bonus por precisión
        $accuracyBonus = 0;
        if ($accuracy >= 95) {
            $accuracyBonus = 15;
        } elseif ($accuracy >= 90) {
            $accuracyBonus = 10;
        } elseif ($accuracy >= 85) {
            $accuracyBonus = 5;
        }

        // Bonus por tiempo (sesiones más largas dan más XP)
        $timeBonus = floor($timeSeconds / 60) * 3;

        return $baseXP + $wpmBonus + $accuracyBonus + $timeBonus;
    }

    /**
     * Texto de respaldo si falla la IA
     */
    private function getFallbackText($level)
    {
        $texts = [
            'beginner' => 'El gato subió al tejado. La luna brilla en el cielo. Los niños juegan en el parque.',
            'intermediate' => 'La tecnología avanza rápidamente en el siglo XXI. Las computadoras son más potentes cada año. La inteligencia artificial cambia nuestras vidas.',
            'advanced' => 'La implementación de algoritmos de machine learning requiere un entendimiento profundo de matemáticas, estadística y programación. Los desarrolladores deben considerar la eficiencia computacional y la escalabilidad.',
        ];

        return $texts[$level] ?? $texts['beginner'];
    }

    /**
     * Mostrar lecciones disponibles
     */
    public function lessons()
    {
        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        // Obtener todas las lecciones ordenadas por nivel y orden
        $lessons = TypingLesson::orderBy('sort_order')->get();

        // Obtener progreso del usuario para cada lección
        $userProgress = UserLessonProgress::where('user_id', $user->id)
            ->pluck('typing_lesson_id')
            ->toArray();

        // Agrupar lecciones por nivel
        $groupedLessons = $lessons->groupBy('level');

        return view('typing.lessons', [
            'user' => $user,
            'gameProgress' => $gameProgress,
            'groupedLessons' => $groupedLessons,
            'userProgress' => $userProgress,
            'pageTitle' => 'TypeMaster AI - Lecciones',
        ]);
    }

    /**
     * Mostrar una lección específica
     */
    public function lesson($id)
    {
        $user = Auth::user();
        $lesson = TypingLesson::findOrFail($id);

        // Verificar si el usuario puede acceder a esta lección
        if (! $lesson->canUserAccess($user->id)) {
            return redirect()->route('typing.lessons')
                ->with('error', 'Debes completar las lecciones anteriores primero.');
        }

        // Obtener progreso del usuario en esta lección
        $userProgress = UserLessonProgress::where('user_id', $user->id)
            ->where('typing_lesson_id', $lesson->id)
            ->first();

        // Redirigir a la vista de práctica con parámetros de lección
        return view('typing.play', [
            'lesson' => $lesson,
            'userProgress' => $userProgress,
            'level' => $lesson->level,
            'mode' => 'lesson',
            'adjustedTargetWpm' => $user->getAdjustedTargetWpm($lesson->target_wpm),
            'pageTitle' => "TypeMaster AI - {$lesson->title}",
        ]);
    }

    /**
     * Guardar progreso de lección
     */
    public function saveLessonProgress(Request $request)
    {
        try {
            $request->validate([
                'lesson_id' => 'required|exists:typing_lessons,id',
                'wpm' => 'required|numeric|min:0',
                'accuracy' => 'required|numeric|min:0|max:100',
                'time_taken' => 'required|integer|min:1',
            ]);

            $user = Auth::user();
            $lesson = TypingLesson::findOrFail($request->lesson_id);

            // Verificar acceso
            if (! $lesson->canUserAccess($user->id)) {
                return response()->json(['error' => 'No tienes acceso a esta lección'], 403);
            }

            // Buscar o crear progreso
            $progress = UserLessonProgress::firstOrCreate(
                ['user_id' => $user->id, 'typing_lesson_id' => $lesson->id],
                ['attempts' => 0]
            );

            // Actualizar intentos
            $progress->attempts++;

            // Actualizar mejores resultados
            if ($request->wpm > $progress->best_wpm) {
                $progress->best_wpm = $request->wpm;
            }
            if ($request->accuracy > $progress->best_accuracy) {
                $progress->best_accuracy = $request->accuracy;
            }

            // Verificar si la lección fue completada (usando objetivos ajustados por edad)
            $targetWpm = $user->getAdjustedTargetWpm($lesson->target_wpm);
            $targetAccuracy = $lesson->target_accuracy;

            $isCompleted = $request->wpm >= $targetWpm && $request->accuracy >= $targetAccuracy;

            if ($isCompleted && ! $progress->completed_at) {
                $progress->completed_at = now();
                $progress->completed = true;

                // Crear notificación de lección completada
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'lesson_completed',
                    'title' => '¡Lección Completada!',
                    'message' => "Has completado la lección: {$lesson->title}",
                    'data' => json_encode(['typing_lesson_id' => $lesson->id]),
                ]);

                // Otorgar XP por completar lección
                $xpGained = $this->calculateLessonXP($lesson, $request->wpm, $request->accuracy);
                $gameProgress = $user->gameProgress;
                $gameProgress->addXP($xpGained);
            }

            $progress->save();

            // Crear sesión de mecanografía
            TypingSession::create([
                'user_id' => $user->id,
                'mode' => 'lesson',
                'level' => $lesson->level,
                'text_used' => $lesson->content,
                'wpm' => $request->wpm,
                'accuracy' => $request->accuracy,
                'time_seconds' => $request->time_taken,
                'errors' => $request->get('errors_count', 0),
                'total_keystrokes' => $request->get('total_keystrokes', 0),
                'correct_keystrokes' => $request->get('correct_keystrokes', 0),
                'xp_earned' => $xpGained ?? 0,
                'error_analysis' => json_encode([
                    'typing_lesson_id' => $lesson->id,
                    'lesson_title' => $lesson->title,
                ]),
            ]);

            // Trackear actividad para sistema de engagement
            $this->trackActivity(
                $user->id,
                'typing',
                $request->time_taken,
                $xpGained ?? 0,
                $this->calculateSessionQuality($request->time_taken, $xpGained ?? 0)
            );

            return response()->json([
                'success' => true,
                'completed' => $isCompleted,
                'xp_gained' => $xpGained ?? 0,
                'message' => $isCompleted ? '¡Lección completada!' : 'Progreso guardado',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al guardar progreso: '.$e->getMessage()], 500);
        }
    }

    /**
     * Calcular XP ganado por completar lección
     */
    private function calculateLessonXP($lesson, $wpm, $accuracy)
    {
        $baseXP = 50; // XP base por completar lección

        // Bonus por superar objetivos
        $wpmBonus = max(0, ($wpm - $lesson->target_wpm) * 2);
        $accuracyBonus = max(0, ($accuracy - $lesson->target_accuracy) * 3);

        // Bonus por nivel de dificultad
        $levelMultiplier = match ($lesson->level) {
            'beginner' => 1.0,
            'intermediate' => 1.5,
            'advanced' => 2.0,
            default => 1.0
        };

        return intval(($baseXP + $wpmBonus + $accuracyBonus) * $levelMultiplier);
    }

    // ====== SPRINT 3: NUEVOS MODOS DE JUEGO ======

    /**
     * Mostrar selector de modos de juego
     */
    public function gameModes()
    {
        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        return view('typing.modes', [
            'user' => $user,
            'gameProgress' => $gameProgress,
            'pageTitle' => 'Modos de Juego - TypeMaster AI',
        ]);
    }

    /**
     * Modo Entrenamiento - Práctica por filas del teclado
     */
    public function trainingMode()
    {
        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        return view('typing.modes.training', [
            'user' => $user,
            'gameProgress' => $gameProgress,
            'pageTitle' => 'Modo Entrenamiento - TypeMaster AI',
        ]);
    }

    /**
     * Modo Arcade - Palabras que caen estilo Tetris
     */
    public function arcadeMode()
    {
        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        return view('typing.modes.arcade', [
            'user' => $user,
            'gameProgress' => $gameProgress,
            'pageTitle' => 'Modo Arcade - TypeMaster AI',
        ]);
    }

    /**
     * Modo Supervivencia - Sistema de vidas y dificultad progresiva
     */
    public function survivalMode()
    {
        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        return view('typing.modes.survival', [
            'user' => $user,
            'gameProgress' => $gameProgress,
            'pageTitle' => 'Modo Supervivencia - TypeMaster AI',
        ]);
    }

    /**
     * Modo Zen - Ambiente relajado con música
     */
    public function zenMode()
    {
        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        return view('typing.modes.zen', [
            'user' => $user,
            'gameProgress' => $gameProgress,
            'pageTitle' => 'Modo Zen - TypeMaster AI',
        ]);
    }

    /**
     * Guardar puntuación de modo de juego
     */
    public function saveModeScore(Request $request)
    {
        $request->validate([
            'mode' => 'required|string|in:training,arcade,survival,zen',
            'score' => 'required|integer|min:0',
            'wpm' => 'required|numeric|min:0',
            'accuracy' => 'required|numeric|min:0|max:100',
            'time_played' => 'required|integer|min:1',
            'extra_data' => 'nullable|array',
        ]);

        $user = Auth::user();
        $gameProgress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        // Calcular XP basado en el modo y rendimiento
        $xpGained = $this->calculateModeXP($request->mode, $request->score, $request->wpm, $request->accuracy);

        // Guardar el nivel anterior para detectar level up
        $previousLevel = $gameProgress->level;

        // Usar el método addXP para manejar correctamente el sistema de niveles
        $levelUp = $gameProgress->addXP($xpGained);

        // Crear sesión de typing para el modo
        $session = TypingSession::create([
            'user_id' => $user->id,
            'mode' => $request->mode,
            'game_mode' => $request->mode,
            'mode_score' => $request->score,
            'mode_data' => json_encode($request->extra_data ?? []),
            'level' => 'intermediate', // Los modos de juego son nivel intermedio por defecto
            'theme' => $request->mode,
            'text_used' => 'Modo de juego: '.ucfirst($request->mode),
            'wpm' => $request->wpm,
            'accuracy' => $request->accuracy,
            'errors' => intval((100 - $request->accuracy) / 100 * ($request->wpm * $request->time_played / 12)),
            'time_seconds' => $request->time_played,
            'total_keystrokes' => intval($request->wpm * $request->time_played / 12), // Aproximación
            'correct_keystrokes' => intval($request->accuracy / 100 * ($request->wpm * $request->time_played / 12)),
            'xp_earned' => $xpGained,
            'completed' => true,
            'session_date' => now(),
        ]);

        // Crear notificaciones
        if ($xpGained > 0) {
            $message = "Has ganado {$xpGained} XP. WPM: {$request->wpm}, Precisión: {$request->accuracy}%";
            if ($levelUp) {
                $message .= " ¡Has subido al nivel {$gameProgress->level}!";
            }

            Notification::create([
                'user_id' => $user->id,
                'title' => $levelUp ? '¡Nivel Alcanzado!' : '¡Modo '.ucfirst($request->mode).' Completado!',
                'message' => $message,
                'type' => $levelUp ? 'level_up' : 'mode_completed',
                'icon' => $levelUp ? 'fas fa-star' : 'fas fa-gamepad',
                'color' => $levelUp ? '#ffd700' : '#28a745',
            ]);
        }

        // Trackear actividad para sistema de engagement
        $this->trackActivity(
            $user->id,
            'typing',
            $request->time_played,
            $xpGained,
            $this->calculateSessionQuality($request->time_played, $xpGained)
        );

        // Incrementar contador de sesiones semanales
        $gameProgress->incrementWeeklySessions();

        // Actualizar engagement score y churn risk
        $gameProgress->updateChurnRiskLevel();

        // Actualizar racha del usuario
        $streakService = app(StreakService::class);
        $streakInfo = $streakService->updateStreak($user->id);

        // Verificar y otorgar logros automáticamente
        $achievementService = app(AchievementService::class);
        $newAchievements = $achievementService->checkAndGrantAchievements($user);

        // Refrescar el modelo para obtener los valores actualizados
        $gameProgress->refresh();

        return response()->json([
            'success' => true,
            'xp_earned' => $xpGained,
            'new_level' => $gameProgress->level,
            'level' => $gameProgress->level,
            'total_xp' => $gameProgress->total_xp,
            'level_up' => $levelUp,
            'current_xp' => $gameProgress->current_xp,
            'required_xp' => $gameProgress->required_xp,
            'current_level_xp' => $gameProgress->current_xp,
            'xp_to_next_level' => $gameProgress->required_xp,
            'xp_needed' => $gameProgress->required_xp,
            'session_id' => $session->id,
            'streak' => [
                'current' => $streakInfo['current_streak'],
                'longest' => $streakInfo['longest_streak'],
                'status' => $streakInfo['streak_status'] ?? 'maintained',
            ],
            'new_achievements' => $newAchievements,
            'achievements_count' => count($newAchievements),
            'message' => $levelUp ? "¡Felicidades! Has alcanzado el nivel {$gameProgress->level}" : "¡Excelente! Has ganado {$xpGained} XP.",
        ]);
    }

    /**
     * Calcular XP para modos de juego
     */
    private function calculateModeXP($mode, $score, $wpm, $accuracy)
    {
        $baseXP = 30;

        // Multiplicadores por modo
        $modeMultiplier = match ($mode) {
            'training' => 1.0,
            'arcade' => 1.2,
            'survival' => 1.5,
            'zen' => 0.8,
            default => 1.0
        };

        // Bonus por rendimiento
        $performanceBonus = ($wpm * 0.5) + ($accuracy * 0.3) + ($score * 0.01);

        return intval(($baseXP + $performanceBonus) * $modeMultiplier);
    }

    /**
     * Mostrar dashboard de engagement del usuario
     * Sprint 7-8: Engagement & Retention System
     */
    public function engagementDashboard()
    {
        $user = Auth::user();
        $progress = $user->gameProgress ?? UserGameProgress::create(['user_id' => $user->id]);

        // Actualizar métricas antes de mostrar
        $progress->updateChurnRiskLevel();

        // Obtener actividades recientes
        $recentActivities = \App\Models\UserActivityTracker::where('user_id', $user->id)
            ->orderBy('session_start', 'desc')
            ->limit(10)
            ->get();

        // Estadísticas de la semana actual
        $startOfWeek = \Carbon\Carbon::now()->startOfWeek();
        $weekActivities = \App\Models\UserActivityTracker::where('user_id', $user->id)
            ->where('session_start', '>=', $startOfWeek)
            ->get();

        // Análisis de calidad de sesiones
        $qualityDistribution = $weekActivities->groupBy('session_quality')->map->count();

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
                'minutes' => round($dayActivities->sum('duration_seconds') / 60),
            ];
        }

        // Distribución por tipo de actividad
        $activityTypes = $recentActivities->groupBy('activity_type')->map->count();

        return view('typing.engagement-dashboard', [
            'user' => $user,
            'progress' => $progress,
            'recentActivities' => $recentActivities,
            'weekActivities' => $weekActivities,
            'qualityDistribution' => $qualityDistribution,
            'dailyProgress' => $dailyProgress,
            'activityTypes' => $activityTypes,
            'pageTitle' => 'Dashboard de Engagement - TypeMaster AI',
        ]);
    }
}
