<?php

use App\Http\Controllers\Api\MobileAgendaController;
use App\Http\Controllers\Api\Integrations\HermesReminderController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileChatController;
use App\Http\Controllers\Api\MobileContextualReminderController;
use App\Http\Controllers\Api\MobileConversationController;
use App\Http\Controllers\Api\MobileDeviceController;
use App\Http\Controllers\Api\MobileFocusSlotController;
use App\Http\Controllers\Api\MobileImageController;
use App\Http\Controllers\Api\MobileMemoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (mobile companion)
|--------------------------------------------------------------------------
|
| Endpoints para la app Android AsistenteIA. Autenticados con Sanctum
| bearer token. Ver Personales/proyectos/asistente-app/endpoints-backend.md
| en el vault consultas-claude para el diseño completo.
|
*/

Route::prefix('mobile')->middleware('throttle:60,1')->group(function () {
    // Público
    Route::post('/login', [MobileAuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/interactions/{id}/feedback', [\App\Http\Controllers\InteractionFeedbackController::class, 'store'])->whereUuid('id');
        // Auth
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);

        // Chat — sesión efímera para que AIController::generateText pueda
        // llamar session() sin romper (cliente móvil es stateless).
        Route::post('/chat', [MobileChatController::class, 'chat'])
            ->middleware([\Illuminate\Session\Middleware\StartSession::class]);

        // Chat con imagen adjunta — usa GPT-4o Vision y persiste en conversación
        // para que el contexto quede en memoria como texto.
        Route::post('/chat/image', [MobileChatController::class, 'chatWithImage'])
            ->middleware([\Illuminate\Session\Middleware\StartSession::class]);

        // Conversaciones
        Route::get('/conversations', [MobileConversationController::class, 'index']);
        Route::get('/conversations/{id}', [MobileConversationController::class, 'show']);
        Route::delete('/conversations/{id}', [MobileConversationController::class, 'destroy']);
        Route::delete('/conversations', [MobileConversationController::class, 'clearAll']);

        // Imágenes
        Route::post('/images/generate', [MobileImageController::class, 'generate'])
            ->middleware('image.limit');
        Route::post('/images/analyze', [MobileImageController::class, 'analyze']);
        // IE1: editar imagen (apagado con IMAGE_EDIT_ENABLED=false)
        Route::post('/images/edit', [\App\Http\Controllers\Api\MobileImageEditController::class, 'edit']);
        Route::get('/images/edits/{id}', [\App\Http\Controllers\Api\MobileImageEditController::class, 'show'])->whereUuid('id');

        // Agenda
        Route::get('/agenda/events', [MobileAgendaController::class, 'index']);
        Route::get('/agenda/upcoming', [MobileAgendaController::class, 'upcoming']);
        Route::get('/agenda/events/{id}', [MobileAgendaController::class, 'show']);
        Route::post('/agenda/events', [MobileAgendaController::class, 'store']);
        Route::put('/agenda/events/{id}', [MobileAgendaController::class, 'update']);
        Route::delete('/agenda/events/{id}', [MobileAgendaController::class, 'destroy']);

        // Slots de mensajes de enfoque (push de voz/texto)
        Route::get('/focus-slots', [MobileFocusSlotController::class, 'index']);
        Route::post('/focus-slots', [MobileFocusSlotController::class, 'store']);
        Route::post('/focus-slots/preview-audio', [MobileFocusSlotController::class, 'previewAudio']);
        Route::put('/focus-slots/{id}', [MobileFocusSlotController::class, 'update']);
        Route::delete('/focus-slots/{id}', [MobileFocusSlotController::class, 'destroy']);

        // Memoria del usuario (user_profile_facts)
        // F6: Hoy y pendientes
        Route::get('/today', [\App\Http\Controllers\Api\MobileTodayController::class, 'today']);
        Route::put('/today/preferences', [\App\Http\Controllers\Api\MobileTodayController::class, 'preferences']);
        Route::get('/tasks', [\App\Http\Controllers\Api\MobileTodayController::class, 'index']);
        Route::post('/tasks', [\App\Http\Controllers\Api\MobileTodayController::class, 'store']);
        Route::patch('/tasks/{id}', [\App\Http\Controllers\Api\MobileTodayController::class, 'update'])->whereNumber('id');
        Route::get('/memory', [MobileMemoryController::class, 'index']);
        Route::delete('/memory/{id}', [MobileMemoryController::class, 'destroy']);
        Route::patch('/memory/{id}', [MobileMemoryController::class, 'update'])->whereNumber('id');
        Route::delete('/memory', [MobileMemoryController::class, 'clear']);
        Route::put('/memory/settings', [MobileMemoryController::class, 'settings']);

        // FCM device tokens
        Route::post('/device-token', [MobileDeviceController::class, 'register']);
        Route::post('/device-token/claim', [MobileDeviceController::class, 'claim']);
        Route::delete('/device-token', [MobileDeviceController::class, 'remove']);

        // Recordatorios contextuales (RC1): consulta y acciones; la app no crea.
        Route::prefix('contextual-reminders')->middleware('reminders.api:mobile')->group(function () {
            Route::get('/', [MobileContextualReminderController::class, 'index']);
            Route::get('/{id}', [MobileContextualReminderController::class, 'show'])->whereUuid('id');
            Route::post('/{id}/complete', [MobileContextualReminderController::class, 'complete'])->whereUuid('id');
            Route::post('/{id}/cancel', [MobileContextualReminderController::class, 'cancel'])->whereUuid('id');
            Route::post('/{id}/snooze', [MobileContextualReminderController::class, 'snooze'])->whereUuid('id');
            Route::post('/{id}/receipts', [MobileContextualReminderController::class, 'receipt'])->whereUuid('id');
            Route::get('/{id}/audio', [MobileContextualReminderController::class, 'audio'])->whereUuid('id');
        });
    });
});

/*
| API dedicada de Hermes (plan RC). Credencial opaca de integración, no
| Sanctum; flag apagado por defecto (config/reminders.php).
*/
Route::prefix('integrations/hermes/v1/reminders')->middleware('reminders.api:hermes')->group(function () {
    Route::get('/', [HermesReminderController::class, 'index'])->middleware('reminders.integration:reminders:read');
    Route::post('/', [HermesReminderController::class, 'store'])->middleware('reminders.integration:reminders:create');
    Route::get('/{id}', [HermesReminderController::class, 'show'])->middleware('reminders.integration:reminders:read');
    Route::post('/{id}/cancel', [HermesReminderController::class, 'cancel'])->middleware('reminders.integration:reminders:cancel');
    Route::post('/{id}/snooze', [HermesReminderController::class, 'snooze'])->middleware('reminders.integration:reminders:snooze');
    Route::post('/{id}/complete', [HermesReminderController::class, 'complete'])->middleware('reminders.integration:reminders:complete');
});
