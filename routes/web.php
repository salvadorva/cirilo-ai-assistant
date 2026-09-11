<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\AIExerciseController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CreativeModeController;
use App\Http\Controllers\ExerciseResultController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageAnalysisController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

// Rutas de prueba
Route::get('/test-buttons', function () {
    return view('courses.test_buttons');
})->name('test.buttons');

// Ruta de prueba para verificar configuración de features
Route::get('/test-config', function () {
    return response()->json([
        'agenda_enabled' => config('features.agenda_enabled'),
        'tutor_enabled' => config('features.tutor_enabled'),
        'config_file_content' => file_get_contents(config_path('features.php')),
    ]);
})->name('test.config');

// Ruta de prueba para verificar el menú visualmente
Route::get('/test-menu', function () {
    return view('test_menu');
})->name('test.menu');

// Ruta para la versión simplificada de la vista de sesión
Route::get('/test-session/{course}/{session}', function ($courseId, $sessionId) {
    $course = \App\Models\Course::findOrFail($courseId);
    $session = \App\Models\CourseSession::where('course_id', $courseId)
        ->where('id', $sessionId)
        ->firstOrFail();

    return view('courses.session_fixed', [
        'course' => $course,
        'session' => $session,
        'userId' => \Illuminate\Support\Facades\Auth::id(),
    ]);
})->name('test.session');

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/openai', function () {
    return view('openai');
});

// Nuevas rutas usando AIController (soporta OpenAI y Grok)
Route::post('/generate-text', [AIController::class, 'generateText']);
Route::post('/generate-image', [AIController::class, 'generateImage'])->middleware('image.limit');
Route::post('/text-to-speech', [AIController::class, 'textToSpeech']);
Route::post('/speech-to-text', [AIController::class, 'speechToText']);
Route::post('/switch-provider', [AIController::class, 'switchProvider'])->middleware('auth');

// Rutas públicas para audios estáticos (usadas por el frontend)
Route::get('/audio/static/welcome', [App\Http\Controllers\Admin\StaticAudioController::class, 'getWelcomeAudio']);
Route::get('/audio/static/funny-phrase/{index}', [App\Http\Controllers\Admin\StaticAudioController::class, 'getFunnyPhrase']);

// Rutas principales
Route::get('/', [HomeController::class, 'home_index'])->name('idex_home')->middleware('role:user|admin');
Route::post('/refresh-home-message', [HomeController::class, 'refreshHomeMessage'])->name('home.refresh-message')->middleware('auth');
Route::get('/preguntas', [HomeController::class, 'preguntas'])->name('preguntas')->middleware('role:user|admin');
Route::get('/conversar', [HomeController::class, 'conversar'])->name('conversar')->middleware('role:user|admin');
// Endpoint de refresh de sesión/CSRF — usado por conversar.blade.php como keepalive y recovery de 419
Route::get('/auth/csrf-token', function () {
    return response()->json(['token' => csrf_token()])
        ->header('Cache-Control', 'no-store, no-cache');
})->middleware('auth');
Route::get('/generate-image-form', [HomeController::class, 'generate_image_form'])->name('generate.image.form')->middleware('role:user|admin');
Route::get('/imagenes', [HomeController::class, 'imagenes'])->name('create_image')->middleware(['role:user|admin', 'image.limit']);

// Nuevas rutas para modo creativo
Route::get('/modo-creativo', [HomeController::class, 'creative_mode'])->name('creative_mode')->middleware('role:user|admin');
Route::post('/generate-creative-idea', [CreativeModeController::class, 'generateIdea'])->middleware('auth');
Route::post('/save-creative-conversation', [CreativeModeController::class, 'saveCreativeConversation'])->middleware('auth');

// Rutas para el modo creativo
Route::post('/creative-mode/generate', [App\Http\Controllers\CreativeModeController::class, 'generate'])->name('creative.generate');
Route::post('/creative-mode/save', [App\Http\Controllers\CreativeModeController::class, 'save'])->name('creative.save');

// Rutas del modo Tutor
// Usamos el middleware feature para proteger todas las rutas del tutor
Route::middleware(['feature:tutor'])->group(function () {
    Route::get('/tutor', [\App\Http\Controllers\TutorController::class, 'index'])->name('tutor')->middleware('role:user|admin');
    Route::post('/tutor/refresh-message', [\App\Http\Controllers\TutorController::class, 'refreshMessage'])->name('tutor.refresh-message')->middleware('auth');
    Route::get('/tutor/curso-ingles', [\App\Http\Controllers\TutorController::class, 'englishCourse'])->name('tutor.english')->middleware('role:user|admin');
    Route::get('/tutor/evaluacion', [\App\Http\Controllers\TutorController::class, 'evaluation'])->name('tutor.evaluation')->middleware('role:user|admin');
    Route::post('/tutor/evaluacion', [\App\Http\Controllers\TutorController::class, 'storeEvaluation'])->name('tutor.evaluation.submit')->middleware('role:user|admin');
    Route::get('/tutor/ejercicios/{level?}', [\App\Http\Controllers\TutorController::class, 'generateExercises'])->name('tutor.exercises')->middleware('role:user|admin');
    Route::get('/tutor/practica/{type}/{level}', [\App\Http\Controllers\TutorController::class, 'practice'])->name('tutor.practice')->middleware('role:user|admin');
    Route::post('/tutor/guardar-progreso', [\App\Http\Controllers\TutorController::class, 'saveExerciseProgress'])->name('tutor.save.progress')->middleware('role:user|admin');
    Route::post('/tutor/evaluar-speaking', [\App\Http\Controllers\TutorController::class, 'evaluateSpeaking'])->name('tutor.evaluate.speaking')->middleware('role:user|admin');
    Route::post('/tutor/generar-audio-instrucciones', [\App\Http\Controllers\TutorController::class, 'generateInstructionsAudio'])->name('tutor.generate.instructions.audio')->middleware('role:user|admin');
    Route::post('/tutor/recomendaciones', [\App\Http\Controllers\TutorController::class, 'generateTutorRecommendations'])->name('tutor.recommendations')->middleware('role:user|admin');
    Route::post('/tutor/evaluar-vocabulario', [\App\Http\Controllers\TutorController::class, 'evaluateVocabulary'])->name('tutor.evaluate.vocabulary')->middleware('role:user|admin');
    Route::post('/tutor/evaluar-gramatica', [\App\Http\Controllers\TutorController::class, 'evaluateGrammar'])->name('tutor.evaluate.grammar')->middleware('role:user|admin');
    Route::post('/tutor/evaluar-listening', [\App\Http\Controllers\TutorController::class, 'evaluateListening'])->name('tutor.evaluate.listening')->middleware('role:user|admin');
    Route::post('/tutor/generar-ejercicio-personalizado', [\App\Http\Controllers\AIExerciseController::class, 'generatePersonalizedExercises'])->name('tutor.generate.personalized')->middleware('role:user|admin');

    // Rutas para cursos personalizados
    Route::get('/cursos', [\App\Http\Controllers\CourseController::class, 'index'])->name('courses.index')->middleware('role:user|admin');
    Route::post('/cursos', [\App\Http\Controllers\CourseController::class, 'create'])->name('courses.create')->middleware('role:user|admin');
    Route::get('/cursos/{course}', [\App\Http\Controllers\CourseController::class, 'show'])->name('courses.show')->middleware('role:user|admin');
    Route::delete('/cursos/{course}', [\App\Http\Controllers\CourseController::class, 'destroy'])->name('courses.destroy')->middleware('role:user|admin');
    Route::get('/cursos/{course}/sesion/{session}', [\App\Http\Controllers\CourseController::class, 'showSession'])->name('courses.session')->middleware('role:user|admin');
    Route::post('/cursos/{course}/sesion/{session}/completar', [\App\Http\Controllers\CourseController::class, 'completeSession'])->name('courses.complete.session')->middleware('role:user|admin');
    Route::post('/cursos/{course}/completar-recursos', [\App\Http\Controllers\CourseController::class, 'completeResources'])->name('courses.complete.resources')->middleware('role:user|admin');
    Route::post('/cursos/{course}/validar-completitud', [\App\Http\Controllers\CourseController::class, 'validateCompleteness'])->name('courses.validate.completeness')->middleware('role:user|admin');
    Route::post('/cursos/{course}/sesion/{session}/generar-recursos', [\App\Http\Controllers\CourseController::class, 'generateSessionResources'])->name('courses.generate.session.resources')->middleware('role:user|admin');

    // API endpoint to check if a course was created by topic and user
    Route::get('/api/check-course', [\App\Http\Controllers\CourseController::class, 'checkCourse'])->name('api.check.course')->middleware('role:user|admin');

    // Dashboard Unificado de Juegos (Reemplaza al index anterior)
    Route::get('/juegos', [\App\Http\Controllers\EngagementController::class, 'dashboard'])->name('games.index')->middleware('role:user|admin');
    Route::post('/games/refresh-summary', [\App\Http\Controllers\EngagementController::class, 'refreshSummary'])->name('games.refresh-summary')->middleware('auth');

    // Rutas para Juegos de Inglés (Movido a subruta)
    Route::get('/juegos/ingles', [\App\Http\Controllers\EnglishGamesController::class, 'index'])->name('games.english')->middleware('role:user|admin');
    Route::get('/juegos/word-match-rush', [\App\Http\Controllers\EnglishGamesController::class, 'wordMatchRush'])->name('games.word-match-rush')->middleware('role:user|admin');
    Route::get('/juegos/sentence-builder', [\App\Http\Controllers\EnglishGamesController::class, 'sentenceBuilder'])->name('games.sentence-builder')->middleware('role:user|admin');
    Route::get('/juegos/vocabulary-shooter', [\App\Http\Controllers\EnglishGamesController::class, 'vocabularyShooter'])->name('games.vocabulary-shooter')->middleware('role:user|admin');
    Route::get('/juegos/vocabulary-shooter/words', [\App\Http\Controllers\EnglishGamesController::class, 'getVocabularyShooterWords'])->name('games.vocabulary-shooter.words')->middleware('role:user|admin');
    Route::get('/juegos/grammar-runner', [\App\Http\Controllers\EnglishGamesController::class, 'grammarRunner'])->name('games.grammar-runner')->middleware('role:user|admin');
    Route::get('/juegos/grammar-runner/questions', [\App\Http\Controllers\EnglishGamesController::class, 'getGrammarRunnerQuestions'])->name('games.grammar-runner.questions')->middleware('role:user|admin');
    Route::get('/juegos/listening-challenge', [\App\Http\Controllers\EnglishGamesController::class, 'listeningChallenge'])->name('games.listening-challenge')->middleware('role:user|admin');
    Route::get('/juegos/listening-challenge/exercises', [\App\Http\Controllers\EnglishGamesController::class, 'getListeningChallengeExercises'])->name('games.listening-challenge.exercises')->middleware('role:user|admin');
    Route::post('/juegos/guardar-resultado', [\App\Http\Controllers\EnglishGamesController::class, 'saveGameResult'])->name('games.save-result')->middleware('role:user|admin');
});

// Nuevas rutas para análisis de imágenes
Route::get('/analisis-imagen', [HomeController::class, 'image_analysis'])->name('image_analysis')->middleware('role:user|admin');
Route::post('/analyze-image', [ImageAnalysisController::class, 'analyzeImage'])->middleware('auth');
Route::post('/save-image-analysis', [ImageAnalysisController::class, 'saveImageAnalysis'])->middleware('auth');

// Nuevas rutas para historial de conversaciones
Route::get('/historial', [HomeController::class, 'conversations_history'])->name('conversations_history')->middleware('role:user|admin');
Route::get('/conversaciones', [ConversationController::class, 'index'])->name('conversations.index')->middleware('auth');
Route::get('/conversaciones/{id}', [ConversationController::class, 'show'])->name('conversations.show')->middleware('auth');
Route::post('/conversaciones', [ConversationController::class, 'store'])->name('conversations.store')->middleware('auth');
Route::put('/conversaciones/{id}', [ConversationController::class, 'update'])->name('conversations.update')->middleware('auth');
Route::delete('/conversaciones/{id}', [ConversationController::class, 'destroy'])->name('conversations.destroy')->middleware('auth');

// Rutas para API de conversaciones
Route::get('/conversations', [ConversationController::class, 'getAll'])->name('conversations.get')->middleware('auth');
Route::get('/conversations/{id}', [ConversationController::class, 'get'])->name('conversations.get.one')->middleware('auth');
Route::delete('/conversations/clear', [ConversationController::class, 'clearAll'])->name('conversations.clear')->middleware('auth');
Route::get('/conversations/{id}/continue', [ConversationController::class, 'continueConversation'])->name('conversations.continue')->middleware('auth');

Route::get('/login', [LoginController::class, 'login'])->name('login');
Route::post('/loginCheck', [LoginController::class, 'check_login'])->name('login_check');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout_session');
Route::post('/save-age', [LoginController::class, 'saveAge'])->name('save.age')->middleware('auth');
// Route for CSRF token refresh - used by JavaScript to update tokens
Route::get('/refresh-csrf', [LoginController::class, 'refreshToken'])->name('csrf.refresh.token');

Route::get('/h2', function () {
    return view('home.NewHome');
})->name('home');

use App\Http\Controllers\Admin\StaticAudioController;
use App\Http\Controllers\Admin\UserController;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/toggle-notifications', [UserController::class, 'toggleEmailNotifications'])->name('users.toggle-notifications');

    // Dashboard Administrativo de Engagement
    Route::get('/dashboard', [\App\Http\Controllers\AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/users-data', [\App\Http\Controllers\AdminDashboardController::class, 'getUsersData'])->name('users.data');
    Route::get('/export-metrics', [\App\Http\Controllers\AdminDashboardController::class, 'exportMetrics'])->name('export-metrics');
    Route::get('/users/{id}/analytics', [\App\Http\Controllers\AdminDashboardController::class, 'userAnalytics'])->name('users.analytics');

    // Gestión de Audios Estáticos
    Route::get('/static-audios', [StaticAudioController::class, 'index'])->name('audios.index');
    Route::get('/static-audios/{id}', [StaticAudioController::class, 'show'])->name('audios.show');
    Route::post('/static-audios/{id}/regenerate', [StaticAudioController::class, 'regenerate'])->name('audios.regenerate');
    Route::put('/static-audios/{id}', [StaticAudioController::class, 'updateText'])->name('audios.update');
    Route::post('/static-audios/{id}/approve', [StaticAudioController::class, 'approve'])->name('audios.approve');
    Route::post('/static-audios/{id}/toggle-active', [StaticAudioController::class, 'toggleActive'])->name('audios.toggle-active');
    Route::post('/static-audios', [StaticAudioController::class, 'addFunnyPhrase'])->name('audios.store');
    Route::delete('/static-audios/{id}', [StaticAudioController::class, 'delete'])->name('audios.delete');
});

// Rutas para el historial de ejercicios
Route::middleware(['feature:tutor'])->group(function () {
    Route::middleware(['auth'])->prefix('tutor')->name('tutor.')->group(function () {
        Route::get('/generate-ai/{level}', [AIExerciseController::class, 'generateExercisesView'])->name('generate.ai');

        // Rutas para historial y estadísticas de ejercicios
        Route::get('/exercise-history', [ExerciseResultController::class, 'index'])->name('exercise.history');
        Route::get('/exercise-result/{id}', [ExerciseResultController::class, 'show'])->name('exercise.show');
        Route::delete('/exercise-result/{id}', [ExerciseResultController::class, 'destroy'])->name('exercise.destroy');
        Route::get('/exercise-statistics', [ExerciseResultController::class, 'statistics'])->name('exercise.statistics');
    });
});

// Rutas del módulo Agenda
// Usamos el middleware feature para proteger todas las rutas de la agenda
Route::middleware(['feature:agenda_enabled'])->group(function () {
    Route::middleware(['auth'])->prefix('agenda')->name('agenda.')->group(function () {
        // Vista principal de la agenda
        Route::get('/', [AgendaController::class, 'index'])->name('index');

        // API para obtener eventos
        Route::get('/events', [AgendaController::class, 'getEvents'])->name('events');
        Route::get('/events/{id}', [AgendaController::class, 'show'])->name('show');

        // CRUD de eventos
        Route::post('/events', [AgendaController::class, 'store'])->name('store');
        Route::put('/events/{id}', [AgendaController::class, 'update'])->name('update');
        Route::delete('/events/{id}', [AgendaController::class, 'destroy'])->name('destroy');

        // Operaciones sobre series de eventos recurrentes
        Route::put('/series/{seriesId}', [AgendaController::class, 'updateSeries'])->name('series.update');
        Route::delete('/series/{seriesId}', [AgendaController::class, 'destroySeries'])->name('series.destroy');

        // Sincronización con Nextcloud CalDAV
        Route::post('/events/{id}/sync-nextcloud',       [AgendaController::class, 'syncToNextcloud'])->name('sync.nextcloud.event');
        Route::post('/series/{seriesId}/sync-nextcloud', [AgendaController::class, 'syncSeriesToNextcloud'])->name('sync.nextcloud.series');
        Route::get('/nextcloud/test-connection',          [AgendaController::class, 'testNextcloudConnection'])->name('nextcloud.test');

        // Ruta para el asistente de voz simple
        Route::post('/process-voice', [AgendaController::class, 'processVoiceRequest'])->name('process.voice');

        // Notificaciones locales PWA (sin VAPID) — polled desde la página
        Route::get('/upcoming-notifications', [AgendaController::class, 'upcomingNotifications'])->name('upcoming.notifications');
    });
});

// Rutas PWA (Progressive Web App)
Route::get('/offline', [\App\Http\Controllers\PWAController::class, 'offline'])->name('pwa.offline');
Route::get('/manifest.json', [\App\Http\Controllers\PWAController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [\App\Http\Controllers\PWAController::class, 'serviceWorker'])->name('pwa.sw');
Route::get('/pwa/install', [\App\Http\Controllers\PWAController::class, 'install'])->name('pwa.install');
Route::get('/pwa/check', [\App\Http\Controllers\PWAController::class, 'checkInstallation'])->name('pwa.check');
Route::post('/pwa/notification', [\App\Http\Controllers\PWAController::class, 'sendNotification'])->name('pwa.notification');
Route::get('/pwa-icons/{filename}', [\App\Http\Controllers\PWAController::class, 'icon'])->where('filename', '.*\.(png|svg|ico)$')->name('pwa.icon');
Route::get('/screenshots/{filename}', [\App\Http\Controllers\PWAController::class, 'screenshot'])->where('filename', '.*\.png$')->name('pwa.screenshot');

// Rutas de notificaciones (requieren autenticación)
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread', [\App\Http\Controllers\NotificationController::class, 'getUnread'])->name('notifications.unread');
    Route::post('/notifications/{id}/mark-read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notifications/cleanup', [\App\Http\Controllers\NotificationController::class, 'cleanup'])->name('notifications.cleanup');

    // Solo para desarrollo
    Route::post('/notifications/test', [\App\Http\Controllers\NotificationController::class, 'createTest'])->name('notifications.test');
});

// Rutas de TypeMaster AI (requieren autenticación)
Route::middleware(['auth'])->prefix('typing')->name('typing.')->group(function () {
    Route::get('/', [\App\Http\Controllers\TypeMasterController::class, 'index'])->name('index');
    Route::get('/play', [\App\Http\Controllers\TypeMasterController::class, 'play'])->name('play');
    Route::post('/generate-text', [\App\Http\Controllers\TypeMasterController::class, 'generateText'])->name('generate.text');
    Route::post('/save-session', [\App\Http\Controllers\TypeMasterController::class, 'saveSession'])->name('save.session');
    Route::get('/stats', [\App\Http\Controllers\TypeMasterController::class, 'showStats'])->name('stats');

    // Rutas de lecciones
    Route::get('/lessons', [\App\Http\Controllers\TypeMasterController::class, 'lessons'])->name('lessons');
    Route::get('/lesson/{id}', [\App\Http\Controllers\TypeMasterController::class, 'lesson'])->name('lesson');
    Route::post('/lesson/save-progress', [\App\Http\Controllers\TypeMasterController::class, 'saveLessonProgress'])->name('lesson.save.progress');

    // Sprint 3: Nuevos modos de juego
    Route::get('/modes', [\App\Http\Controllers\TypeMasterController::class, 'gameModes'])->name('modes');
    Route::get('/mode/training', [\App\Http\Controllers\TypeMasterController::class, 'trainingMode'])->name('mode.training');
    Route::get('/mode/arcade', [\App\Http\Controllers\TypeMasterController::class, 'arcadeMode'])->name('mode.arcade');
    Route::get('/mode/survival', [\App\Http\Controllers\TypeMasterController::class, 'survivalMode'])->name('mode.survival');
    Route::get('/mode/zen', [\App\Http\Controllers\TypeMasterController::class, 'zenMode'])->name('mode.zen');
    Route::post('/mode/save-score', [\App\Http\Controllers\TypeMasterController::class, 'saveModeScore'])->name('mode.save.score');
});

// Dashboard de Engagement Unificado (para todos los juegos)
Route::middleware(['auth', 'role:user|admin'])->group(function () {
    // Ruta principal del dashboard unificado
    Route::get('/engagement', [\App\Http\Controllers\EngagementController::class, 'dashboard'])->name('engagement');
    Route::get('/engagement/stats', [\App\Http\Controllers\EngagementController::class, 'quickStats'])->name('engagement.stats');
});

// Rutas de Perfil y Configuración de Usuario
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [\App\Http\Controllers\UserProfileController::class, 'show'])->name('profile');
    Route::post('/profile/update', [\App\Http\Controllers\UserProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [\App\Http\Controllers\UserProfileController::class, 'updateAvatar'])->name('profile.avatar');

    Route::get('/settings', [\App\Http\Controllers\UserSettingsController::class, 'show'])->name('settings');
    Route::post('/settings/update', [\App\Http\Controllers\UserSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/password', [\App\Http\Controllers\UserSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/telegram-test', [\App\Http\Controllers\UserSettingsController::class, 'testTelegram'])->name('settings.telegram.test');
    Route::post('/settings/nextcloud',      [\App\Http\Controllers\UserSettingsController::class, 'updateNextcloud'])->name('settings.nextcloud');
    Route::get('/settings/nextcloud-test',  [\App\Http\Controllers\UserSettingsController::class, 'testNextcloud'])->name('settings.nextcloud.test');
    Route::get('/settings/memory', [\App\Http\Controllers\UserSettingsController::class, 'getMemory'])->name('settings.memory');
    Route::delete('/settings/memory/{id}', [\App\Http\Controllers\UserSettingsController::class, 'deleteFact'])->name('settings.memory.delete');
    Route::delete('/settings/memory', [\App\Http\Controllers\UserSettingsController::class, 'clearMemory'])->name('settings.memory.clear');
});

// Rutas de Administración - Bitácora de APIs
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/api-usage', [\App\Http\Controllers\Admin\ApiUsageController::class, 'index'])->name('api-usage.index');
    Route::get('/api-usage/export', [\App\Http\Controllers\Admin\ApiUsageController::class, 'export'])->name('api-usage.export');
    Route::get('/api-usage/stats', [\App\Http\Controllers\Admin\ApiUsageController::class, 'stats'])->name('api-usage.stats');
    Route::get('/api-usage/{id}', [\App\Http\Controllers\Admin\ApiUsageController::class, 'show'])->name('api-usage.show');
});
