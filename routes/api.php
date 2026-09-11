<?php

use App\Http\Controllers\Api\MobileAgendaController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileChatController;
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
        Route::get('/memory', [MobileMemoryController::class, 'index']);
        Route::delete('/memory/{id}', [MobileMemoryController::class, 'destroy']);

        // FCM device tokens
        Route::post('/device-token', [MobileDeviceController::class, 'register']);
        Route::delete('/device-token', [MobileDeviceController::class, 'remove']);
    });
});
