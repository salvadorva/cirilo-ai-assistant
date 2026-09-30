<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

class HomeController extends Controller
{
    public function home_index()
    {
        // echo "Hola Mundo";

        // Establece el locale a español
        Carbon::setLocale('es');

        // Obtiene la fecha actual formateada
        $currentDate = Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY');

        // Puedes pasar esta fecha a tu vista o utilizarla como necesites
        $user = auth()->user();
        $role = $user && $user->role ? $user->role->name : null;

        // Obtener datos de engagement si existen
        $progress = $user->gameProgress ?? null;
        $hasEngagementData = $progress !== null;

        // --- Mensaje personalizado de Cirilo (Dashboard) ---
        $cacheKey = 'dashboard_welcome_'.$user->id;
        $ciriloMessage = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (! $ciriloMessage) {
            // Determinar si es usuario nuevo
            $isNewUser = ! $user->last_login_at || $user->created_at->diffInDays(now()) < 1;

            // Datos de login
            $daysSinceLogin = $user->last_login_at ? now()->diffInDays($user->last_login_at) : 0;

            // Última conversación con el asistente
            $lastConversation = $user->conversations()->orderBy('created_at', 'desc')->first();
            $totalConversations = $user->conversations()->count();

            // Actividad en juegos de inglés
            $lastGame = \DB::table('english_game_sessions')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();
            $totalGamesPlayed = \DB::table('english_game_sessions')
                ->where('user_id', $user->id)
                ->count();

            // Datos del tutor
            $userLevel = \App\Models\UserEnglishLevel::where('user_id', $user->id)->first();
            $userCourses = \App\Models\Course::where('user_id', $user->id)->count();
            $totalExercises = $user->exerciseResults()->count();

            // Construir contexto enriquecido para el dashboard
            $context = [
                'name' => $user->name,
                'age' => $user->age,
                'scope' => 'dashboard',
                'is_new_user' => $isNewUser,

                // Login
                'days_since_login' => $daysSinceLogin,

                // Asistente Virtual
                'total_conversations' => $totalConversations,
                'last_conversation_date' => $lastConversation ? $lastConversation->created_at->diffForHumans() : null,
                'last_conversation_topic' => $lastConversation ? $lastConversation->title : null,

                // Juegos
                'total_games_played' => $totalGamesPlayed,
                'last_game_type' => $lastGame ? $lastGame->game_type : null,
                'last_game_score' => $lastGame ? $lastGame->score : null,
                'last_game_date' => $lastGame ? Carbon::parse($lastGame->created_at)->diffForHumans() : null,

                // Tutor/Inglés
                'english_level' => $userLevel ? $userLevel->level : 'Sin evaluar',
                'total_exercises' => $totalExercises,
                'custom_courses_count' => $userCourses,
            ];

            // Llamar al AIController
            $aiController = new \App\Http\Controllers\AIController;
            $request = new \Illuminate\Http\Request(['context' => $context]);
            $response = $aiController->generateTutorWelcome($request);
            $data = json_decode($response->getContent(), true);

            if ($data['success'] ?? false) {
                $ciriloMessage = [
                    'message' => $data['message'],
                    'audioUrl' => $data['audioUrl'],
                    'generated_at' => now()->toIso8601String(),
                ];
                // Guardar en caché por 1 hora
                \Illuminate\Support\Facades\Cache::put($cacheKey, $ciriloMessage, 3600);
            }
        }
        // --- Fin Mensaje Cirilo ---

        // F6-01: compromisos y pendientes de hoy al inicio (datos reales; no dependen del mensaje generado).
        try {
            $today = app(\App\Services\Tasks\TodaySummary::class)->for($user);
        } catch (\Throwable $e) {
            report($e);
            $today = null;
        }

        return view('home.index', compact('currentDate', 'user', 'role', 'progress', 'hasEngagementData', 'ciriloMessage', 'today'));
    }

    /**
     * Forzar actualización del mensaje de Cirilo en el dashboard
     */
    public function refreshHomeMessage()
    {
        $user = auth()->user();
        $cacheKey = 'dashboard_welcome_'.$user->id;

        // Borrar caché
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        // Determinar si es usuario nuevo
        $isNewUser = ! $user->last_login_at || $user->created_at->diffInDays(now()) < 1;

        // Datos de login
        $daysSinceLogin = $user->last_login_at ? now()->diffInDays($user->last_login_at) : 0;

        // Última conversación con el asistente
        $lastConversation = $user->conversations()->orderBy('created_at', 'desc')->first();
        $totalConversations = $user->conversations()->count();

        // Actividad en juegos de inglés
        $lastGame = \DB::table('english_game_sessions')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();
        $totalGamesPlayed = \DB::table('english_game_sessions')
            ->where('user_id', $user->id)
            ->count();

        // Datos del tutor
        $userLevel = \App\Models\UserEnglishLevel::where('user_id', $user->id)->first();
        $userCourses = \App\Models\Course::where('user_id', $user->id)->count();
        $totalExercises = $user->exerciseResults()->count();

        // Construir contexto enriquecido para el dashboard
        $context = [
            'name' => $user->name,
            'age' => $user->age,
            'scope' => 'dashboard',
            'is_new_user' => $isNewUser,

            // Login
            'days_since_login' => $daysSinceLogin,

            // Asistente Virtual
            'total_conversations' => $totalConversations,
            'last_conversation_date' => $lastConversation ? $lastConversation->created_at->diffForHumans() : null,
            'last_conversation_topic' => $lastConversation ? $lastConversation->title : null,

            // Juegos
            'total_games_played' => $totalGamesPlayed,
            'last_game_type' => $lastGame ? $lastGame->game_type : null,
            'last_game_score' => $lastGame ? $lastGame->score : null,
            'last_game_date' => $lastGame ? Carbon::parse($lastGame->created_at)->diffForHumans() : null,

            // Tutor/Inglés
            'english_level' => $userLevel ? $userLevel->level : 'Sin evaluar',
            'total_exercises' => $totalExercises,
            'custom_courses_count' => $userCourses,
        ];

        // Llamar al AIController
        $aiController = new \App\Http\Controllers\AIController;
        $request = new \Illuminate\Http\Request(['context' => $context]);
        $response = $aiController->generateTutorWelcome($request);
        $data = json_decode($response->getContent(), true);

        if ($data['success'] ?? false) {
            $ciriloMessage = [
                'message' => $data['message'],
                'audioUrl' => $data['audioUrl'],
                'generated_at' => now()->toIso8601String(),
            ];
            // Guardar en caché por 1 hora
            \Illuminate\Support\Facades\Cache::put($cacheKey, $ciriloMessage, 3600);

            return response()->json([
                'success' => true,
                'summary' => $ciriloMessage,
            ]);
        }

        return response()->json(['success' => false, 'error' => 'No se pudo generar el mensaje'], 500);
    }

    public function preguntas()
    {

        Carbon::setLocale('es');

        // Obtiene la fecha actual formateada
        $currentDate = Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY');

        $user = auth()->user();
        $role = $user && $user->role ? $user->role->name : null;

        return view('home.preguntas', compact('currentDate', 'user', 'role'));
    }

    public function conversar()
    {
        $user = auth()->user();
        $role = $user && $user->role ? $user->role->name : null;

        return view('home.conversar', compact('user', 'role'));
    }

    public function generate_image_form()
    {
        Carbon::setLocale('es');
        $currentDate = Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY');

        $user = auth()->user();
        $role = $user && $user->role ? $user->role->name : null;

        return view('home.generate-image', compact('currentDate', 'user', 'role'));
    }

    /**
     * Mostrar la vista del modo creativo.
     */
    public function creative_mode()
    {
        Carbon::setLocale('es');
        $currentDate = Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY');

        $user = auth()->user();
        $role = $user && $user->role ? $user->role->name : null;

        return view('home.creative_mode', compact('currentDate', 'user', 'role'));
    }

    /**
     * Mostrar la vista de análisis de imágenes.
     */
    public function image_analysis()
    {
        Carbon::setLocale('es');
        $currentDate = Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY');

        $user = auth()->user();
        $role = $user && $user->role ? $user->role->name : null;

        return view('home.image_analysis', compact('currentDate', 'user', 'role'));
    }

    /**
     * Mostrar la vista del historial de conversaciones.
     */
    public function conversations_history()
    {
        Carbon::setLocale('es');
        $currentDate = Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY');

        $user = auth()->user();
        $role = $user && $user->role ? $user->role->name : null;

        // Obtener las conversaciones del usuario
        $conversations = \App\Models\Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('home.conversation_history', compact('currentDate', 'user', 'role', 'conversations'));
    }

    public function imagenes()
    {
        Carbon::setLocale('es');
        $currentDate = Carbon::now()->isoFormat('dddd, D [de] MMMM [de] YYYY');

        $user = auth()->user();
        $role = $user && $user->role ? $user->role->name : null;

        // Obtener información del límite de imágenes del middleware
        $imagesRemaining = request()->get('images_remaining', 4);
        $imagesUsed = request()->get('images_used', 0);
        $dailyLimit = request()->get('daily_limit', 4);

        return view('home.generate-image', compact(
            'currentDate',
            'user',
            'role',
            'imagesRemaining',
            'imagesUsed',
            'dailyLimit'
        ));
    }
}
