<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\AIController;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationHistory;
use App\Services\ConversationSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Support\AiLog as Log;
use Illuminate\Support\Facades\Storage;

/**
 * Wrapper móvil del chat. Reutiliza toda la lógica de AIController::generateText
 * (memoria del usuario, intent detection de calendario / imagen, dual provider)
 * y agrega persistencia del par user/assistant en la tabla messages.
 *
 * Dos endpoints:
 *   POST /api/mobile/chat        — JSON, chat texto puro (+ generación de imagen
 *                                   si se detecta intent en el prompt).
 *   POST /api/mobile/chat/image  — multipart, chat con imagen adjunta. Usa
 *                                   GPT-4o Vision y persiste el análisis como
 *                                   mensaje del assistant para que las preguntas
 *                                   de seguimiento tengan contexto vía texto.
 */
class MobileChatController extends Controller
{
    public function chat(Request $request, AIController $aiController): JsonResponse
    {
        $request->validate([
            'prompt'          => 'required|string|max:8000',
            'conversation_id' => 'nullable|integer',
            'history'         => 'nullable|array',
            'generateAudio'   => 'nullable|boolean',
            'voice'           => 'nullable|string|in:alloy,echo,fable,nova,onyx,shimmer',
        ]);

        $user = $request->user();
        $prompt = $request->input('prompt');
        $conversationId = $request->input('conversation_id');

        $conversation = $this->resolveConversation($user, $conversationId, $prompt);
        if ($conversation['created']) {
            $request->merge(['conversation_id' => $conversation['model']->id]);
        }
        $conversationModel = $conversation['model'];

        // Persistir mensaje del usuario antes de llamar al modelo
        Message::create([
            'conversation_id' => $conversationModel->id,
            'role'            => 'user',
            'content'         => $prompt,
        ]);

        $request->headers->set('X-Cirilo-Source', 'mobile');
        $response = $aiController->generateText($request);
        $data = $response->getData(true);

        if (! $response->isSuccessful()) {
            return $response;
        }

        $reply = $data['choices'][0]['message']['content'] ?? '';
        $providerUsed = $data['provider_used'] ?? ($user->ai_provider ?? 'openai');
        $audioUrl = $data['audioUrl'] ?? null;
        $imageGenerated = $data['image_generated'] ?? null;

        // Si se generó imagen vía intent, incluir marker en el mensaje persistido
        // para que el contexto quede en memoria (Cirilo recordará que generó imagen).
        $persistedReply = $reply;
        if ($imageGenerated) {
            $persistedReply .= "\n\n[Imagen generada — prompt: \"".($imageGenerated['prompt_used'] ?? '')."\"]";
        }

        if ($persistedReply !== '') {
            Message::create([
                'conversation_id' => $conversationModel->id,
                'role'            => 'assistant',
                'content'         => $persistedReply,
            ]);
        }

        $this->syncConversationContent($conversationModel);
        $this->summarizeAfterResponse($conversationModel);

        Log::info('[MobileChat] user_id='.$user->id.' conv_id='.$conversationModel->id.' tokens='.($data['usage']['total_tokens'] ?? 'n/a'));

        return response()->json([
            'reply'           => $reply,
            'conversation_id' => $conversationModel->id,
            'provider_used'   => $providerUsed,
            'audio_url'       => $audioUrl,
            // F2-06: la app lee event_created; el backend web lo llama calendar_event_created.
            'event_created'   => $data['event_created'] ?? $data['calendar_event_created'] ?? null,
            'agenda'          => $data['agenda'] ?? null,
            'image_generated' => $imageGenerated,
        ]);
    }

    /**
     * Chat con imagen adjunta. Multipart:
     *   image: file (jpeg/png, max 5MB)
     *   prompt: string opcional (default: "¿qué hay en esta imagen?")
     *   conversation_id: int opcional
     */
    public function chatWithImage(Request $request): JsonResponse
    {
        $request->validate([
            'image'           => 'required|image|max:5120',
            'prompt'          => 'nullable|string|max:8000',
            'conversation_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $rawPrompt = trim($request->input('prompt') ?? '');
        $userQuestion = $rawPrompt !== '' ? $rawPrompt : '¿Qué hay en esta imagen?';
        $conversationId = $request->input('conversation_id');

        $conversation = $this->resolveConversation($user, $conversationId, $userQuestion);
        $conversationModel = $conversation['model'];

        // Guardar imagen permanentemente
        $imagePath = $request->file('image')->store('images/chat', 'public');
        $fullPath = Storage::disk('public')->path($imagePath);
        $imageBase64 = base64_encode(file_get_contents($fullPath));
        $imageUrl = Storage::disk('public')->url($imagePath);

        // Persistir mensaje del usuario con marker de imagen adjunta
        // (así futuras lecturas del historial saben que hubo una imagen)
        $persistedUserContent = "[Imagen adjunta] $userQuestion";
        Message::create([
            'conversation_id' => $conversationModel->id,
            'role'            => 'user',
            'content'         => $persistedUserContent,
        ]);

        // Llamar a GPT-4o Vision
        $userPersonalPrompt = $user->prompt ?? '';
        $visionPrompt = $userQuestion;
        if ($userPersonalPrompt) {
            $visionPrompt = "$userPersonalPrompt\n\nPregunta del usuario sobre la imagen: $userQuestion";
        }

        try {
            $startTime = microtime(true);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.openai.api_key'),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('ai.models.vision'),
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $visionPrompt],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => "data:image/jpeg;base64,{$imageBase64}",
                        ]],
                    ],
                ]],
                'max_tokens' => 800,
            ]);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            if (! $response->successful()) {
                Log::error('[MobileChat] Vision API falló: '.$response->body());
                return response()->json([
                    'reply'           => 'Lo siento, no pude analizar la imagen. Intentá de nuevo.',
                    'conversation_id' => $conversationModel->id,
                ], 500);
            }

            $data = $response->json();
            $analysis = $data['choices'][0]['message']['content'] ?? '';

            // Persistir respuesta del assistant (el análisis de la imagen).
            // Esto queda en la conversación, así futuras preguntas tienen contexto.
            Message::create([
                'conversation_id' => $conversationModel->id,
                'role'            => 'assistant',
                'content'         => $analysis,
            ]);

            $this->syncConversationContent($conversationModel);

            Log::info('[MobileChat-Image] user_id='.$user->id.' conv_id='.$conversationModel->id.' time_ms='.$responseTime);

            return response()->json([
                'reply'           => $analysis,
                'conversation_id' => $conversationModel->id,
                'provider_used'   => 'openai',
                'audio_url'       => null,
                'event_created'   => null,
                'image_generated' => null,
                'image_url'       => $imageUrl, // URL de la imagen que se subió (referencia)
            ]);
        } catch (\Throwable $e) {
            Log::error('[MobileChat-Image] Excepción: '.$e->getMessage());
            return response()->json([
                'reply'           => 'Ocurrió un error procesando la imagen.',
                'conversation_id' => $conversationModel->id,
            ], 500);
        }
    }

    /**
     * Devuelve la conversación existente (validando ownership) o crea una nueva.
     * Returns ['model' => Conversation, 'created' => bool]
     */
    private function resolveConversation($user, ?int $conversationId, string $promptForTitle): array
    {
        $conversation = $conversationId
            ? Conversation::where('id', $conversationId)->where('user_id', $user->id)->firstOrFail()
            : null;

        if (! $conversation) {
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'title'   => mb_substr($promptForTitle, 0, 60),
                'type'    => 'chat',
                'content' => json_encode(['messages' => []]),
            ]);
            return ['model' => $conversation, 'created' => true];
        }

        return ['model' => $conversation, 'created' => false];
    }

    /**
     * F4-03: el móvil nunca disparaba el resumen ni la extracción de memoria. Corre al terminar la
     * petición (después de enviar la respuesta con php-fpm), sin depender de un worker de colas.
     */
    private function summarizeAfterResponse(Conversation $conversation): void
    {
        app()->terminating(function () use ($conversation) {
            try {
                $messages = json_decode($conversation->content ?? '{}', true)['messages'] ?? [];
                app(ConversationSummaryService::class)->maybeSummarize($conversation, $messages);
            } catch (\Throwable $e) {
                Log::warning('[MobileChat] resumen no generado', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);
            }
        });
    }

    private function syncConversationContent(Conversation $conversation): void
    {
        ConversationHistory::refreshContent($conversation);
    }
}
