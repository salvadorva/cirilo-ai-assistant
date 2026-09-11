<?php

namespace App\Http\Controllers;

use App\Services\MemoryService;
use App\Traits\LogsApiUsage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AIController extends Controller
{
    use LogsApiUsage;

    /**
     * Estima la cantidad de tokens en un texto.
     * Regla general: ~4 caracteres por token en español/inglés.
     */
    private function estimateTokens($text)
    {
        if (empty($text)) {
            return 0;
        }

        // Estimación simple: 1 token ~= 4 caracteres
        return ceil(strlen($text) / 4);
    }

    private function getApiCredentials($provider)
    {
        switch ($provider) {
            case 'openai':
                return [
                    'api_key' => config('services.openai.api_key'),
                    'base_url' => 'https://api.openai.com/v1',
                ];
            case 'grok':
                return [
                    'api_key' => config('services.grok.api_key'),
                    'base_url' => 'https://api.x.ai/v1',
                ];
            default:
                throw new \Exception('Proveedor de IA no soportado: '.$provider);
        }
    }

    public function generateText(Request $request)
    {
        $user = auth()->user();
        $provider = $user->ai_provider ?? 'openai';
        $prompt = $request->input('prompt');
        $history = $request->input('history', []); // Obtener el historial de conversación
        $generateAudio = $request->input('generateAudio', true); // Por defecto, generar audio
        $currentConversationId = $request->input('conversation_id'); // ID de la sesión activa

        // Voz TTS de OpenAI: configurable por request, default 'echo'.
        // Voces válidas: alloy, echo, fable, nova, onyx, shimmer.
        $allowedVoices = ['alloy', 'echo', 'fable', 'nova', 'onyx', 'shimmer'];
        $voice = $request->input('voice', 'echo');
        if (! in_array($voice, $allowedVoices, true)) {
            $voice = 'echo';
        }

        // Verificar si es una pregunta sobre fecha u hora actual
        $lowerPrompt = strtolower($prompt);
        if (
            preg_match('/(qué|que) (día|hora|fecha) es( hoy)?/i', $lowerPrompt) ||
            preg_match('/dime (el día|la hora|la fecha)( actual| de hoy)?/i', $lowerPrompt) ||
            preg_match('/cuál es (el día|la hora|la fecha)( actual| de hoy)?/i', $lowerPrompt)
        ) {

            // Establecer zona horaria a CST (Central Standard Time)
            date_default_timezone_set('America/Guatemala');

            // Obtener fecha y hora del servidor
            $now = new \DateTime;
            $dayNames = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
            $monthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

            $dayOfWeek = $dayNames[$now->format('w')];
            $day = $now->format('j');
            $month = $monthNames[$now->format('n') - 1];
            $year = $now->format('Y');
            $time = $now->format('H:i');

            // Construir respuesta
            $response = '';
            if (strpos($lowerPrompt, 'hora') !== false) {
                $response = "Son las $time horas.";
            } elseif (strpos($lowerPrompt, 'día') !== false || strpos($lowerPrompt, 'fecha') !== false) {
                $response = "Hoy es $dayOfWeek $day de $month de $year.";
            } else {
                $response = "Hoy es $dayOfWeek $day de $month de $year y son las $time horas.";
            }

            // Construir la respuesta en formato compatible con la API
            $data = [
                'choices' => [
                    [
                        'message' => [
                            'content' => $response,
                        ],
                    ],
                ],
                'provider_used' => 'servidor_local',
            ];

            // Generar audio si se solicita
            if ($generateAudio && ! empty($response)) {
                try {
                    // Usar OpenAI para text-to-speech
                    $openaiCredentials = $this->getApiCredentials('openai');

                    $audioResponse = Http::withHeaders([
                        'Authorization' => 'Bearer '.$openaiCredentials['api_key'],
                        'Content-Type' => 'application/json',
                    ])->post($openaiCredentials['base_url'].'/audio/speech', [
                        'model' => 'tts-1',
                        'input' => $response,
                        'voice' => $voice,
                        'output_format' => 'mp3',
                    ]);

                    if ($audioResponse->successful()) {
                        Log::info('Audio generado exitosamente para la respuesta de fecha/hora');

                        $fileName = 'audio/'.uniqid().'.mp3';
                        Storage::disk('public')->put($fileName, $audioResponse->body());

                        $audioUrl = Storage::disk('public')->url($fileName);
                        $data['audioUrl'] = $audioUrl;
                    }
                } catch (\Exception $e) {
                    Log::error('Excepción al generar audio para fecha/hora: '.$e->getMessage());
                }
            }

            return response()->json($data);
        }

        // Limit history to last 10 messages to save tokens and context window
        $maxHistory = 10;
        if (count($history) > $maxHistory) {
            $history = array_slice($history, -$maxHistory);
        }

        // Personalización de prompt según usuario y System Prompt
        $systemPrompt = "Eres un asistente virtual profesional, amigable y altamente eficiente. Tu objetivo principal es ayudar al usuario resolviendo sus dudas y completando tareas con precisión.\n\n";

        $systemPrompt .= "### Reglas de Comportamiento:\n";
        $systemPrompt .= "1. **Idioma**: Responde siempre en español, salvo que el usuario te pida explícitamente otro idioma.\n";
        $systemPrompt .= "2. **Tono**: Mantén un tono cordial, respetuoso y profesional, pero cercano.\n";
        $systemPrompt .= "3. **Formato**: Utiliza Markdown para estructurar tus respuestas. Usa **negritas** para resaltar conceptos clave, listas para enumerar pasos y bloques de código para ejemplos técnicos.\n";
        $systemPrompt .= "4. **Concisión**: Sé directo. Evita preámbulos innecesarios. Si la respuesta requiere detalle, estructura la información claramente.\n";
        $systemPrompt .= "5. **Honestidad**: Si no sabes la respuesta o no tienes información suficiente, admítelo. No inventes información.\n";
        $systemPrompt .= "6. **Código**: Si proporcionas código, asegúrate de que sea funcional, moderno y siga las mejores prácticas. Comenta el código cuando sea necesario para explicar la lógica.\n";
        $systemPrompt .= "7. **Agenda**: NUNCA confirmes que creaste, guardaste o agendaste un evento a menos que el sistema te indique explícitamente que fue guardado exitosamente. Si el usuario pide agendar algo y no recibes confirmación del sistema, respóndele: 'Para que pueda crearlo, escríbeme algo como: **agéndame [nombre del evento]** con los detalles.' No inventes ni simules haber guardado nada.\n\n";
        if ($user && $user->hasNextcloud() && $request->header('X-Cirilo-Source') !== 'mobile') {
            $systemPrompt .= "8. **Nextcloud**: Cuando el sistema confirme que se guardó un evento, tu respuesta debe terminar indicando brevemente que el sistema le preguntará si desea sincronizarlo con su Nextcloud. Ejemplo: '...ya está guardado. Ahora te pregunto si lo sincronizo con tu Nextcloud.'\n\n";
        }

        $userPrompt = $user && $user->prompt ? $user->prompt : '';
        if ($userPrompt) {
            $systemPrompt .= "### Instrucciones Personalizadas del Usuario:\n".$userPrompt."\n\n";
        }

        $systemPrompt .= "### Contexto del Usuario:\n";
        $systemPrompt .= '- **Nombre**: '.($user ? $user->name : 'Usuario')."\n";
        if ($user && $user->role) {
            $systemPrompt .= '- **Rol**: '.$user->role->name."\n";
        }

        // Inyectar perfil del usuario y conversaciones recientes via MemoryService
        if ($user) {
            $contextBlock = MemoryService::buildContextBlock($user->id, $currentConversationId);
            if ($contextBlock) {
                $systemPrompt .= $contextBlock . "\n";
            }
        }

        // Flujo de generación de imagen desde el chat
        $generatedImage = null;
        if ($user && $this->detectImageGenerationIntent($prompt)) {
            try {
                $imageRequest = new Request(['prompt' => $prompt]);
                $imageRequest->setUserResolver(fn () => $user);
                $imageResponse = $this->generateImage($imageRequest);
                $imageData = $imageResponse->getData(true);
                $imageUrl = $imageData['image_url'] ?? null;
                if ($imageUrl) {
                    $generatedImage = [
                        'url'         => $imageUrl,
                        'prompt_used' => $prompt,
                    ];
                    $systemPrompt .= "\n### Imagen generada exitosamente:\n";
                    $systemPrompt .= "Acabas de generar una imagen para el usuario basada en su petición. ";
                    $systemPrompt .= "La imagen ya se le mostrará al usuario inmediatamente. ";
                    $systemPrompt .= "Tu respuesta debe ser breve, cálida y confirmar que la imagen está lista. ";
                    $systemPrompt .= "No describas la imagen en detalle (el usuario la verá). ";
                    $systemPrompt .= "Ejemplo: '¡Listo! Aquí está tu imagen 🎨'.\n";
                } else {
                    $errMsg = $imageData['error'] ?? 'no se pudo generar';
                    $systemPrompt .= "\n### Intento de generación de imagen fallido:\n";
                    $systemPrompt .= "El usuario pidió generar una imagen pero falló: {$errMsg}. ";
                    $systemPrompt .= "Discúlpate brevemente y sugiere reformular el prompt.\n";
                }
            } catch (\Throwable $e) {
                Log::error('[AIController] Error generando imagen desde chat: '.$e->getMessage());
            }
        }

        // Flujo de creación de eventos desde el chat
        $createdEvent = null;
        $createdCount = 0;
        $recurrenceSummary = null;
        $askForEventDetails = false;
        $pendingEventCreation = session('pending_calendar_event_creation', false);

        // En cliente móvil la sesión es efímera (Bearer token sin cookies), así que el
        // flag de "creación pendiente" no persiste entre turnos. Lo derivamos del
        // historial: si Cirilo acaba de pedir los datos de un evento y hubo intención
        // de crear en los últimos turnos, seguimos completando ese evento.
        if (! $pendingEventCreation
            && $request->header('X-Cirilo-Source') === 'mobile'
            && ! empty($history)) {
            $last = end($history);
            $lastIsDetailRequest = is_array($last)
                && ($last['role'] ?? '') === 'assistant'
                && $this->looksLikeEventDetailRequest($last['content'] ?? '');
            if ($lastIsDetailRequest) {
                foreach (array_slice($history, -6) as $m) {
                    if (($m['role'] ?? '') === 'user'
                        && $this->detectCalendarCreateIntent($m['content'] ?? '')) {
                        $pendingEventCreation = true;
                        break;
                    }
                }
            }
        }

        if ($user && ($this->detectCalendarCreateIntent($prompt) || $pendingEventCreation)) {
            $openaiCredentials = $this->getApiCredentials('openai');

            // Dar contexto de la conversación reciente al extractor: combina los datos
            // que el usuario fue dando en varios turnos (clave en mobile, donde no hay
            // sesión que recuerde el evento a medio crear).
            $extractionContext = $prompt;
            if (! empty($history)) {
                $recentHistory = array_slice($history, -6);
                $contextLines = '';
                foreach ($recentHistory as $msg) {
                    $role = $msg['role'] === 'user' ? 'Usuario' : 'Asistente';
                    $contextLines .= "{$role}: {$msg['content']}\n";
                }
                $extractionContext = $contextLines."Usuario: {$prompt}";
            }

            $eventData = $this->extractEventDataFromPrompt($extractionContext, $openaiCredentials['api_key']);

            if ($eventData && $this->isEventDataComplete($eventData)) {
                $creationResult  = $this->createEventFromChat($eventData, $user->id);
                $createdEvent    = $creationResult['event'] ?? null;
                $createdCount    = $creationResult['count'] ?? 0;
                $recurrenceSummary = $creationResult['summary'] ?? null;
                session()->forget('pending_calendar_event_creation');
            } else {
                // Faltan datos — pedir al usuario sin crear nada
                $askForEventDetails = true;
                session(['pending_calendar_event_creation' => true]);
            }
        }

        // Inyectar contexto del calendario si la consulta es relevante
        $calendarContext = $this->getCalendarContext($user, $prompt);
        if ($calendarContext) {
            $systemPrompt .= $calendarContext;
        }

        if ($createdEvent) {
            $tz = 'America/Guatemala';
            $start = \Carbon\Carbon::parse($createdEvent->start_date)->setTimezone($tz);
            $systemPrompt .= "\n### Evento(s) creado(s) exitosamente:\n";
            $systemPrompt .= "Acabas de crear el/los siguiente(s) evento(s) en la agenda del usuario:\n";
            $systemPrompt .= "- **Título:** {$createdEvent->title}\n";
            if ($createdCount > 1 && $recurrenceSummary) {
                $systemPrompt .= "- **Recurrencia:** {$recurrenceSummary}\n";
                $systemPrompt .= "- **Total de eventos creados:** {$createdCount}\n";
                $systemPrompt .= "- **Primer evento:** ".$start->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')." a las ".$start->format('H:i')."\n";
            } else {
                $systemPrompt .= "- **Fecha:** ".$start->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')."\n";
                $systemPrompt .= "- **Hora:** ".($createdEvent->all_day ? 'Todo el día' : $start->format('H:i'))."\n";
            }
            if ($createdEvent->location) {
                $systemPrompt .= "- **Lugar:** {$createdEvent->location}\n";
            }
            if ($createdEvent->reminder_minutes_before > 0) {
                $systemPrompt .= "- **Recordatorio:** {$createdEvent->reminder_minutes_before} minutos antes\n";
            }
            $systemPrompt .= "Confirma al usuario que el/los evento(s) fue(ron) agendado(s) correctamente y puede verlos en su [Agenda](/agenda).\n";
        } elseif ($askForEventDetails) {
            $systemPrompt .= "\n### Crear Evento en Agenda:\n";
            $systemPrompt .= "El usuario quiere crear un evento en su agenda pero no proporcionó todos los datos necesarios. ";
            $systemPrompt .= "Pregúntale de forma amigable y concisa por los datos que falten. ";
            $systemPrompt .= "Los datos mínimos requeridos son: **título del evento**, **fecha** y **hora**. ";
            $systemPrompt .= "Opcionalmente puedes preguntar por lugar, descripción y si desea recordatorio (cuántos minutos antes). ";
            $systemPrompt .= "No inventes datos ni crees el evento todavía — espera a que el usuario confirme los detalles.\n";
        } elseif ($calendarContext) {
            $systemPrompt .= "Si el usuario quiere **crear** un nuevo evento, puedes hacerlo directamente: solo pídele los detalles necesarios.\n";
        }

        try {
            $credentials = $this->getApiCredentials($provider);
            $client = new Client;

            Log::info('Generando texto con proveedor: '.$provider);
            Log::info('Prompt original recibido: '.$prompt);
            Log::info('Historial recibido: '.count($history).' mensajes');

            // Construir mensajes con el historial de conversación
            $messages = [];

            // Agregar System Prompt al inicio
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];

            // Si hay historial, agregarlo primero
            if (! empty($history) && is_array($history)) {
                foreach ($history as $msg) {
                    if (isset($msg['role']) && isset($msg['content'])) {
                        $messages[] = [
                            'role' => $msg['role'],
                            'content' => $msg['content'],
                        ];
                    }
                }
            }

            // Agregar el mensaje actual del usuario
            if ($provider === 'grok') {
                // Para Grok, agregar instrucciones de concisión al mensaje actual
                $enhancedPrompt = 'Responde de manera concisa y directa, con respuestas breves pero completas. Evita incluir múltiples referencias o información excesiva. Limita tu respuesta a lo esencial. Pregunta: '.$prompt;
                Log::info('Prompt mejorado para Grok con instrucciones de concisión');
                $messages[] = ['role' => 'user', 'content' => $enhancedPrompt];
            } else {
                // Para OpenAI, agregar el mensaje actual directamente
                $messages[] = ['role' => 'user', 'content' => $prompt];
            }

            Log::info('Total de mensajes a enviar: '.count($messages));

            $requestData = [
                'headers' => [
                    'Authorization' => 'Bearer '.$credentials['api_key'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messages' => $messages,
                ],
            ];

            // Configuración específica por proveedor
            $endpoint = $credentials['base_url'].'/chat/completions';
            if ($provider === 'openai') {
                // Responses API con web_search_preview — busca en internet cuando la consulta lo requiere
                $inputMessages = array_values(array_filter($messages, fn ($m) => $m['role'] !== 'system'));
                $requestData = [
                    'headers' => [
                        'Authorization' => 'Bearer '.$credentials['api_key'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'gpt-4.1',
                        'instructions' => $systemPrompt,
                        'input' => $inputMessages,
                        'tools' => [['type' => 'web_search_preview', 'search_context_size' => 'medium']],
                        'temperature' => 0.6,
                    ],
                ];
                $endpoint = $credentials['base_url'].'/responses';
            } elseif ($provider === 'grok') {
                // $requestData['json']['model'] = 'grok-4-fast-reasoning';
                $requestData['json']['model'] = 'grok-4-1-fast-reasoning';
                $requestData['json']['max_tokens'] = 2000; // Más tokens para Grok
                $requestData['json']['stream'] = false;
                $requestData['json']['temperature'] = 0.7;

                // Modificar configuración de DeepSearch según la complejidad de la consulta
                // Para pruebas, desactivamos DeepSearch para ver si el problema es específico de esta función
                $useDeepSearch = false; // Cambiar a true cuando se resuelva el problema básico

                if ($useDeepSearch) {
                    $requestData['json']['deep_search'] = true;
                    $requestData['json']['deep_search_timeout_ms'] = 60000; // 60 segundos
                    $requestData['json']['number_of_sources'] = 3;
                    Log::info('DeepSearch activado para Grok con timeout de 60 segundos');
                } else {
                    Log::info('DeepSearch desactivado para Grok en modo de prueba');
                }

                $endpoint = 'https://api.x.ai/v1/chat/completions';
            }

            Log::info('Endpoint que se va a llamar: '.$endpoint);
            Log::info('Modelo configurado: '.$requestData['json']['model']);

            // Configurar cliente HTTP específicamente para Grok con timeouts adecuados y optimizado para AWS
            if ($provider === 'grok') {
                // Crear un nuevo cliente con configuración específica para Grok en entorno AWS
                $client = new Client([
                    'timeout' => 180,  // Timeout total en segundos (aumentado para entornos AWS)
                    'connect_timeout' => 60, // Timeout de conexión (aumentado para entornos AWS)
                    'read_timeout' => 180,   // Timeout de lectura (aumentado para entornos AWS)
                    'http_errors' => false,  // No lanzar excepciones por errores HTTP
                    'verify' => true,        // Verificar certificados SSL
                    'curl' => [
                        CURLOPT_TCP_KEEPALIVE => 1, // Mantener conexión TCP activa
                        CURLOPT_TCP_KEEPIDLE => 60, // Tiempo en segundos antes de enviar keepalive
                        CURLOPT_CONNECTTIMEOUT => 60, // Timeout de conexión para CURL
                        CURLOPT_DNS_CACHE_TIMEOUT => 600, // Cache DNS por 10 minutos
                        CURLOPT_FRESH_CONNECT => false, // Reutilizar conexiones
                        CURLOPT_FORBID_REUSE => false, // Permitir reutilización de conexiones
                    ],
                    'debug' => false,        // Activar solo para diagnóstico
                ]);

                Log::info('Cliente HTTP configurado con parámetros optimizados para AWS y timeout extendido de 180 segundos para Grok');
            }

            // Realizar la solicitud
            $startTime = microtime(true);
            $response = $client->post($endpoint, $requestData);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            $body = $response->getBody();
            $data = json_decode($body, true);

            Log::info('Respuesta del proveedor '.$provider.': '.json_encode($data));

            // Extraer el texto de la respuesta según el proveedor
            $responseText = '';
            if ($provider === 'openai') {
                // Responses API: output[].content[].text
                foreach ($data['output'] ?? [] as $item) {
                    if (($item['type'] ?? '') === 'message') {
                        foreach ($item['content'] ?? [] as $content) {
                            if (($content['type'] ?? '') === 'output_text') {
                                $responseText = $content['text'];
                                break 2;
                            }
                        }
                    }
                }
                // Normalizar al formato choices para el frontend y para logTextGeneration
                $data['choices'] = [['message' => ['content' => $responseText]]];
                // Normalizar usage (Responses API usa input_tokens/output_tokens)
                if (isset($data['usage'])) {
                    $data['usage']['prompt_tokens'] = $data['usage']['input_tokens'] ?? 0;
                    $data['usage']['completion_tokens'] = $data['usage']['output_tokens'] ?? 0;
                }
            } elseif ($provider === 'grok' && isset($data['choices'][0]['message']['content'])) {
                $responseText = $data['choices'][0]['message']['content'];
            }

            // Registrar uso de API (después de normalizar $data para tener tokens correctos)
            $this->logTextGeneration(
                $requestData['json']['model'],
                $prompt,
                $data,
                $provider,
                $responseTime
            );

            // Agregar información del proveedor usado
            $data['provider_used'] = $provider;

            // Generar audio si se solicita
            if ($generateAudio && ! empty($responseText)) {
                try {
                    // Limpiar markdown y truncar para TTS (evita timeouts en respuestas largas)
                    $ttsText = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $responseText); // [text](url) → text
                    $ttsText = preg_replace('/https?:\/\/\S+/', '', $ttsText);              // URLs sueltas
                    $ttsText = preg_replace('/#{1,6}\s+/m', '', $ttsText);                  // headers
                    $ttsText = preg_replace('/\*{1,3}([^*]+)\*{1,3}/', '$1', $ttsText);     // bold/italic
                    $ttsText = preg_replace('/`[^`]+`/', '', $ttsText);                     // inline code
                    $ttsText = trim(preg_replace('/\s{2,}/', ' ', $ttsText));
                    if (mb_strlen($ttsText) > 3000) {
                        $ttsText = mb_substr($ttsText, 0, 3000).'...';
                    }

                    // Siempre usar OpenAI para text-to-speech
                    $openaiCredentials = $this->getApiCredentials('openai');

                    $audioResponse = Http::timeout(60)->withHeaders([
                        'Authorization' => 'Bearer '.$openaiCredentials['api_key'],
                        'Content-Type' => 'application/json',
                    ])->post($openaiCredentials['base_url'].'/audio/speech', [
                        'model' => 'tts-1',
                        'input' => $ttsText,
                        'voice' => $voice,
                        'response_format' => 'mp3',
                    ]);

                    if ($audioResponse->successful()) {
                        Log::info('Audio generado exitosamente para la respuesta de texto');

                        $fileName = 'audio/'.uniqid().'.mp3';
                        Storage::disk('public')->put($fileName, $audioResponse->body());

                        $audioUrl = Storage::disk('public')->url($fileName);
                        $data['audioUrl'] = $audioUrl;
                    } else {
                        Log::error('Error al generar audio: '.$audioResponse->status());
                    }
                } catch (\Exception $e) {
                    Log::error('Excepción al generar audio: '.$e->getMessage());
                    // No fallamos toda la respuesta si solo falla el audio
                }
            }

            // Calcular tokens estimados
            $totalTokens = 0;
            $systemTokens = $this->estimateTokens($systemPrompt);
            $historyTokens = 0;
            $promptTokens = $this->estimateTokens($prompt);
            $responseTokens = $this->estimateTokens($responseText);

            foreach ($messages as $msg) {
                $historyTokens += $this->estimateTokens($msg['content']);
            }

            $totalTokens = $systemTokens + $historyTokens + $promptTokens + $responseTokens;

            // Determinar límite de tokens según el modelo
            $model = $requestData['json']['model'];
            $tokenLimit = 128000; // Valor por defecto (GPT-4o, GPT-4 Turbo, Grok 1.5)

            if (strpos($model, 'gpt-4') !== false) {
                // GPT-4o y GPT-4 Turbo tienen 128k
                $tokenLimit = 128000;
                // Si fuera GPT-4 original (8k) o 32k, habría que ajustar, pero asumimos versiones recientes
            } elseif (strpos($model, 'grok') !== false) {
                // Grok-1.5 tiene 128k context window
                $tokenLimit = 128000;
            } elseif (strpos($model, 'gpt-3.5') !== false) {
                $tokenLimit = 16385;
            }

            $usagePercentage = ($totalTokens / $tokenLimit) * 100;

            $data['token_usage'] = [
                'total' => $totalTokens,
                'limit' => $tokenLimit,
                'percentage' => round($usagePercentage, 2),
                'breakdown' => [
                    'system' => $systemTokens,
                    'history' => $historyTokens,
                    'prompt' => $promptTokens,
                    'response' => $responseTokens,
                ],
                'model' => $model,
            ];

            // Agregar advertencia si supera el 80%
            if ($usagePercentage >= 80) {
                $data['warning'] = [
                    'type' => 'token_limit',
                    'message' => 'Has utilizado el '.round($usagePercentage).'% de la capacidad de memoria ('.number_format($tokenLimit).' tokens). Te sugerimos iniciar una nueva conversación.',
                    'threshold' => 80,
                ];
            }

            // Incluir info del evento creado para que el frontend muestre el modal Nextcloud
            if (! empty($createdEvent)) {
                $data['calendar_event_created'] = [
                    'id'        => $createdEvent->id,
                    'series_id' => $createdEvent->series_id,
                    'title'     => $createdEvent->title,
                    'count'     => $createdCount ?? 1,
                ];
            }

            // Incluir info de imagen generada para que el frontend la renderice
            if (! empty($generatedImage)) {
                $data['image_generated'] = $generatedImage;
            }

            return response()->json($data);
        } catch (RequestException $e) {
            // Registrar detalles del error para diagnóstico
            Log::error('Error en solicitud a '.$provider.': '.$e->getMessage());

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $errorResponse = json_decode($e->getResponse()->getBody(), true);

                // Manejo específico para errores de timeout (504) y otros errores comunes en AWS
                if ($statusCode == 504 && $provider === 'grok') {
                    Log::error('Timeout en Grok desde AWS. Detalles completos del error: '.json_encode($errorResponse));

                    // Intentar cambiar automáticamente a OpenAI como fallback
                    try {
                        Log::info('Intentando fallback a OpenAI debido a timeout en Grok');
                        $openaiCredentials = $this->getApiCredentials('openai');
                        $openaiClient = new Client([
                            'timeout' => 60,
                            'http_errors' => false,
                        ]);

                        $openaiResponse = $openaiClient->post($openaiCredentials['base_url'].'/chat/completions', [
                            'headers' => [
                                'Authorization' => 'Bearer '.$openaiCredentials['api_key'],
                                'Content-Type' => 'application/json',
                            ],
                            'json' => [
                                // 'model' => 'gpt-4o',
                                'model' => 'gpt-4.1',
                                'messages' => [['role' => 'user', 'content' => $prompt]],
                                'max_tokens' => 2000,
                            ],
                        ]);

                        if ($openaiResponse->getStatusCode() == 200) {
                            $openaiData = json_decode($openaiResponse->getBody(), true);
                            $openaiData['provider_used'] = 'openai (fallback)';
                            $openaiData['fallback_reason'] = 'Grok timeout (504)';
                            Log::info('Fallback a OpenAI exitoso');

                            return response()->json($openaiData);
                        }
                    } catch (\Exception $fallbackError) {
                        Log::error('Error en fallback a OpenAI: '.$fallbackError->getMessage());
                    }

                    return response()->json([
                        'error' => 'La solicitud a Grok está tardando demasiado tiempo desde el servidor AWS. Se intentó usar OpenAI como alternativa pero también falló.',
                        'provider' => $provider,
                        'errorCode' => 504,
                        'suggestion' => 'Contacte al administrador o intente más tarde. El servidor puede estar experimentando problemas de conectividad.',
                    ], 504);
                }

                // Manejar otros errores comunes en entornos AWS
                if (in_array($statusCode, [502, 503, 522, 524]) && $provider === 'grok') {
                    Log::error('Error de conectividad en AWS con Grok. Código: '.$statusCode);

                    return response()->json([
                        'error' => 'Hay problemas de conectividad entre el servidor AWS y la API de Grok.',
                        'provider' => $provider,
                        'errorCode' => $statusCode,
                        'suggestion' => 'Intente nuevamente más tarde o use OpenAI como proveedor alternativo.',
                    ], $statusCode);
                }

                return response()->json([
                    'error' => $errorResponse,
                    'provider' => $provider,
                    'errorCode' => $statusCode,
                ], $statusCode);
            }

            return response()->json([
                'error' => 'Hubo un problema con la solicitud a '.$provider.': '.$e->getMessage(),
                'provider' => $provider,
            ], 500);
        }
    }

    public function generateImage(Request $request)
    {
        $user = auth()->user();
        $provider = 'openai'; // Siempre usar OpenAI para generación de imágenes
        $prompt = $request->input('prompt');

        // Personalización de prompt según usuario
        $userPrompt = $user && $user->prompt ? $user->prompt : '';
        if ($userPrompt) {
            $prompt = $userPrompt.' Por cierto, mi nombre es '.($user ? $user->name : '').".\n\nPregunta del usuario: ".$prompt;
        }

        Log::info('Prompt para generación de imagen: '.$prompt);

        try {
            $credentials = $this->getApiCredentials($provider);

            // Configuración avanzada para la solicitud HTTP con manejo de timeout
            $client = new Client([
                'timeout' => 90,  // 90 segundos de timeout total
                'connect_timeout' => 30, // 30 segundos para establecer conexión
                'read_timeout' => 90,  // 90 segundos para leer la respuesta
                'http_errors' => false, // No lanzar excepciones por errores HTTP
                'verify' => true,  // Verificar certificados SSL
                'curl' => [
                    CURLOPT_TCP_KEEPALIVE => 1,
                    CURLOPT_TCP_KEEPIDLE => 30,
                    CURLOPT_CONNECTTIMEOUT => 30,
                    CURLOPT_LOW_SPEED_TIME => 30,
                    CURLOPT_LOW_SPEED_LIMIT => 1,
                ],
            ]);

            Log::info('Enviando solicitud a OpenAI para generación de imagen');

            $startTime = microtime(true);
            $response = $client->post($credentials['base_url'].'/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer '.$credentials['api_key'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-image-1',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'quality' => 'medium',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            $responseData = json_decode($body, true);

            if ($statusCode >= 200 && $statusCode < 300 && isset($responseData['data'][0]['b64_json'])) {
                Log::info('Generación de imagen exitosa con '.$provider);

                $responseTime = (int) ((microtime(true) - $startTime) * 1000);

                // gpt-image-1 devuelve base64 — guardar en storage/public y servir URL propia
                $imageData = base64_decode($responseData['data'][0]['b64_json']);
                $filename = 'images/generated/'.uniqid('img_', true).'.png';
                \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $imageData);
                $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($filename);

                $this->logImageGeneration('gpt-image-1', $prompt, true, null, $responseTime);

                return response()->json([
                    'image_url' => $imageUrl,
                    'provider_used' => $provider,
                ]);
            } else {
                $errorMessage = 'Error al generar la imagen.';

                if (isset($responseData['error']['message']) && ! empty($responseData['error']['message'])) {
                    $errorMessage = $responseData['error']['message'];
                } elseif (isset($responseData['error']['type'])) {
                    if ($responseData['error']['type'] === 'image_generation_user_error') {
                        $errorMessage = 'OpenAI no puede generar esta imagen. Intenta con un prompt diferente.';
                    } else {
                        $errorMessage = 'Error de tipo: '.$responseData['error']['type'];
                    }
                }

                Log::error('Error en la respuesta de '.$provider.': '.$statusCode.' - '.$errorMessage);

                $responseTime = (int) ((microtime(true) - $startTime) * 1000);
                $this->logImageGeneration('gpt-image-1', $prompt, false, $errorMessage, $responseTime);

                return response()->json([
                    'error' => $errorMessage,
                    'provider' => $provider,
                ], $statusCode);
            }
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Error específico de conexión
            Log::error('Error de conexión: '.$e->getMessage());

            return response()->json([
                'error' => 'No se pudo conectar al servidor de OpenAI. Por favor, verifica tu conexión a internet e inténtalo de nuevo.',
                'provider' => $provider,
            ], 503);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Error específico de la solicitud
            $errorMessage = $e->getMessage();

            // Detectar si es un error de timeout
            if (
                stripos($errorMessage, 'timed out') !== false ||
                stripos($errorMessage, 'timeout') !== false ||
                stripos($errorMessage, 'Operation timed out') !== false
            ) {
                Log::error('Error de timeout: '.$errorMessage);

                return response()->json([
                    'error' => 'La solicitud ha tardado demasiado tiempo. Por favor, intenta con un prompt más simple o inténtalo más tarde.',
                    'provider' => $provider,
                ], 504);
            }

            Log::error('Error en la solicitud: '.$errorMessage);

            return response()->json([
                'error' => 'Error en la solicitud a OpenAI: '.$errorMessage,
                'provider' => $provider,
            ], 500);
        } catch (\Exception $e) {
            // Cualquier otro error
            Log::error('Excepción: '.$e->getMessage());

            return response()->json([
                'error' => 'Ocurrió un error inesperado: '.$e->getMessage(),
                'provider' => $provider,
            ], 500);
        }
    }

    public function textToSpeech(Request $request)
    {
        $user = auth()->user();
        $userProvider = $user->ai_provider ?? 'openai';
        $text = $request->input('text');

        // Voz TTS: configurable, default 'echo'. Voces válidas:
        // alloy, echo, fable, nova, onyx, shimmer.
        $allowedVoices = ['alloy', 'echo', 'fable', 'nova', 'onyx', 'shimmer'];
        $voice = $request->input('voice', 'echo');
        if (! in_array($voice, $allowedVoices, true)) {
            $voice = 'echo';
        }

        Log::info('Solicitud de text-to-speech recibida. Longitud del texto: '.strlen($text));

        // Validar que el texto no esté vacío
        if (empty(trim($text))) {
            Log::warning('Solicitud de text-to-speech con texto vacío');

            return response()->json([
                'error' => 'No hay texto para convertir a voz.',
                'provider' => 'openai',
            ], 400);
        }

        // Limitar el texto si es demasiado largo (OpenAI TTS tiene límite de 4096 caracteres)
        $maxLength = 3000;
        if (strlen($text) > $maxLength) {
            Log::warning('Texto para text-to-speech truncado por exceder el límite de '.$maxLength.' caracteres. Longitud original: '.strlen($text));

            // Buscar un punto final para hacer un corte limpio
            $cutPoint = strrpos(substr($text, 0, $maxLength), '.');
            if ($cutPoint === false) {
                // Si no hay punto, buscar un espacio
                $cutPoint = strrpos(substr($text, 0, $maxLength), ' ');
            }
            if ($cutPoint === false) {
                // Si tampoco hay espacio, simplemente cortar
                $cutPoint = $maxLength;
            }

            $text = substr($text, 0, $cutPoint + 1); // +1 para incluir el punto o espacio
            Log::info('Texto truncado a '.strlen($text).' caracteres');
        }

        try {
            // Siempre usar OpenAI para text-to-speech, independientemente del proveedor del usuario
            $credentials = $this->getApiCredentials('openai');

            Log::info('Enviando solicitud a OpenAI TTS API con credenciales: '.substr($credentials['api_key'], 0, 5).'...');

            $startTime = microtime(true);

            // Aumentar el timeout a 120 segundos y agregar reintentos
            $response = Http::timeout(120)
                ->retry(3, 100) // Reintentar 3 veces con 100ms de espera entre intentos
                ->withHeaders([
                    'Authorization' => 'Bearer '.$credentials['api_key'],
                    'Content-Type' => 'application/json',
                ])->post($credentials['base_url'].'/audio/speech', [
                    'model' => 'tts-1',
                    // Asegurarse de que el texto no tenga caracteres problemáticos
                    'input' => mb_convert_encoding($text, 'UTF-8', 'auto'),
                    'voice' => $voice,
                    'output_format' => 'mp3',
                ]);

            if ($response->successful()) {
                $responseTime = (int) ((microtime(true) - $startTime) * 1000);
                Log::info('Respuesta exitosa de OpenAI TTS API. Tamaño del contenido: '.strlen($response->body()).' bytes');

                // Registrar uso exitoso
                $this->logTextToSpeech($text, true, null, $responseTime);

                // Guardar audios dinámicos en carpeta separada para facilitar limpieza
                $fileName = 'audio/dynamic/'.uniqid().'.mp3';

                // Verificar que el directorio de audio dinámico exista
                if (! Storage::disk('public')->exists('audio/dynamic')) {
                    Storage::disk('public')->makeDirectory('audio/dynamic');
                    Log::info('Directorio de audio/dynamic creado en storage/public');
                }

                // Guardar el archivo de audio
                try {
                    Storage::disk('public')->put($fileName, $response->body());
                    Log::info('Archivo de audio guardado en: '.$fileName);
                } catch (\Exception $e) {
                    Log::error('Error al guardar el archivo de audio: '.$e->getMessage());
                    throw new \Exception('Error al guardar el archivo de audio: '.$e->getMessage());
                }

                $audioUrl = Storage::disk('public')->url($fileName);
                Log::info('URL del audio generada: '.$audioUrl);

                return response()->json([
                    'audioUrl' => $audioUrl,
                    'provider_used' => 'openai',
                ]);
            } else {
                $responseData = $response->json();
                $statusCode = $response->status();

                $responseTime = (int) ((microtime(true) - $startTime) * 1000);
                Log::error('Error en la respuesta de OpenAI TTS API. Código: '.$statusCode.', Respuesta: '.json_encode($responseData));

                // Registrar error
                $errorMsg = isset($responseData['error']['message'])
                    ? $responseData['error']['message']
                    : 'Error al generar el audio (Código: '.$statusCode.')';
                $this->logTextToSpeech($text, false, $errorMsg, $responseTime);

                // Mensaje de error más específico
                $errorMessage = isset($responseData['error']['message'])
                    ? $responseData['error']['message']
                    : 'Error al generar el audio (Código: '.$statusCode.')';

                return response()->json([
                    'error' => $errorMessage,
                    'provider' => 'openai',
                    'status_code' => $statusCode,
                ], $statusCode);
            }
        } catch (\Exception $e) {
            Log::error('Excepción en text-to-speech: '.$e->getMessage()."\n".$e->getTraceAsString());

            return response()->json([
                'error' => 'Error al generar audio: '.$e->getMessage(),
                'provider' => 'openai',
            ], 500);
        }
    }

    public function speechToText(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:wav,mp3,m4a,webm,ogg|max:25600', // 25MB max
        ]);

        try {
            // Siempre usar OpenAI para speech-to-text, independientemente del proveedor del usuario
            $credentials = $this->getApiCredentials('openai');
            $audioFile = $request->file('audio');

            Log::info('Iniciando speech-to-text con archivo: '.$audioFile->getClientOriginalName());

            $startTime = microtime(true);
            $audioSize = $audioFile->getSize();
            $client = new Client;
            $maxRetries = 3;
            $attempt = 0;
            $response = null;
            $lastException = null;

            while ($attempt < $maxRetries) {
                try {
                    $attempt++;
                    $response = $client->post($credentials['base_url'].'/audio/transcriptions', [
                        'headers' => [
                            'Authorization' => 'Bearer '.$credentials['api_key'],
                        ],
                        'multipart' => [
                            [
                                'name' => 'file',
                                'contents' => fopen($audioFile->getPathname(), 'r'),
                                'filename' => $audioFile->getClientOriginalName(),
                            ],
                            [
                                'name' => 'model',
                                'contents' => 'whisper-1',
                            ],
                            [
                                'name' => 'language',
                                'contents' => 'es', // Español
                            ],
                        ],
                        'timeout' => 60,
                        'connect_timeout' => 30,
                    ]);

                    // Si llegamos aquí, la solicitud fue exitosa
                    break;
                } catch (\Exception $e) {
                    $lastException = $e;
                    Log::warning("Intento $attempt de speech-to-text fallido: ".$e->getMessage());

                    if ($attempt >= $maxRetries) {
                        throw $e;
                    }

                    // Esperar un poco antes del siguiente intento (backoff exponencial simple)
                    usleep(100000 * $attempt); // 100ms, 200ms...
                }
            }

            if ($response->getStatusCode() === 200) {
                $responseTime = (int) ((microtime(true) - $startTime) * 1000);
                $responseData = json_decode($response->getBody()->getContents(), true);

                // Registrar uso exitoso
                $this->logSpeechToText($audioSize, true, null, $responseTime);

                return response()->json([
                    'text' => $responseData['text'],
                    'provider_used' => 'openai',
                ]);
            } else {
                $responseTime = (int) ((microtime(true) - $startTime) * 1000);

                // Registrar error
                $this->logSpeechToText($audioSize, false, 'Error al transcribir el audio', $responseTime);

                return response()->json([
                    'error' => 'Error al transcribir el audio.',
                    'provider' => 'openai',
                ], $response->getStatusCode());
            }
        } catch (\Exception $e) {
            Log::error('Error en speech-to-text: '.$e->getMessage());

            return response()->json([
                'error' => 'Error al procesar el audio: '.$e->getMessage(),
                'provider' => 'openai',
            ], 500);
        }
    }

    /**
     * Genera un resumen/mensaje de gamificación basado en el contexto del usuario.
     */
    public function generateGamificationSummary(Request $request)
    {
        try {
            $context = $request->input('context');
            $user = auth()->user();

            // Construir el prompt del sistema para el coach
            $systemPrompt = "Eres 'Nexus', un entrenador de IA avanzado y motivador para un sistema de aprendizaje y juegos. ";
            $systemPrompt .= 'Tu objetivo es analizar el progreso del usuario y darle un mensaje corto, personalizado y motivador. ';
            $systemPrompt .= "Reglas:\n";
            $systemPrompt .= "1. Sé breve (máximo 2-3 frases).\n";
            $systemPrompt .= "2. Usa un tono entusiasta pero profesional.\n";
            $systemPrompt .= "3. Si el usuario lleva días sin entrar, anímalo a volver suavemente.\n";
            $systemPrompt .= "4. Si ha subido de nivel o ganado XP recientemente, felicítalo.\n";
            $systemPrompt .= "5. Menciona datos específicos (Nivel, XP) para demostrar que conoces su contexto.\n";
            $systemPrompt .= "6. Recomienda una actividad específica basada en sus estadísticas.\n";
            $systemPrompt .= "7. Responde SIEMPRE en español.\n";

            // Construir el prompt del usuario con el contexto
            $userPrompt = "Analiza este contexto de usuario y genera un mensaje:\n";
            $userPrompt .= '- Nombre: '.($context['name'] ?? 'Usuario')."\n";
            $userPrompt .= '- Nivel: '.($context['level'] ?? 1)."\n";
            $userPrompt .= '- XP Total: '.($context['total_xp'] ?? 0)."\n";
            $userPrompt .= '- Días Offline: '.($context['days_offline'] ?? 0)."\n";
            $userPrompt .= '- Actividad Reciente: '.($context['recent_activity'] ?? 'Ninguna')."\n";
            $userPrompt .= '- Edad: '.($context['age'] ?? 'No especificada')."\n";

            // Reutilizar la lógica de llamada a la API
            // Creamos un request interno simulado
            $internalRequest = new Request([
                'prompt' => $userPrompt,
                'history' => [], // Sin historial para esto
                'system_prompt_override' => $systemPrompt, // Necesitaremos soportar esto en generateText o hacerlo aquí directo
            ]);

            // Para no modificar generateText demasiado, hacemos la llamada directa aquí simplificada
            // o usamos generateText si soporta override.
            // Por simplicidad y robustez, llamaremos a la API directamente aquí usando getApiCredentials

            $credentials = $this->getApiCredentials('openai'); // Usar OpenAI para consistencia en coach

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer '.$credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->post($credentials['base_url'].'/chat/completions', [
                'model' => 'gpt-4.1',
                'messages' => $messages,
                'max_tokens' => 150,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $message = $responseData['choices'][0]['message']['content'];

                // Generar audio automáticamente
                $audioRequest = new Request(['text' => $message]);
                $audioResponse = $this->textToSpeech($audioRequest);
                $audioData = json_decode($audioResponse->getContent(), true);
                $audioUrl = $audioData['audioUrl'] ?? null;

                return response()->json([
                    'message' => $message,
                    'audioUrl' => $audioUrl,
                    'success' => true,
                ]);
            } else {
                throw new \Exception('Error al generar mensaje de coach.');
            }

        } catch (\Exception $e) {
            Log::error('Error en generateGamificationSummary: '.$e->getMessage());

            return response()->json([
                'message' => '¡Hola! Qué bueno verte de nuevo. ¡Vamos a aprender algo nuevo hoy!',
                'audioUrl' => null,
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Genera un mensaje de bienvenida personalizado del tutor IA basado en el progreso del usuario.
     * Soporta scope 'tutor' (solo temas de inglés) y 'dashboard' (conocimiento completo).
     */
    public function generateTutorWelcome(Request $request)
    {
        try {
            $context = $request->input('context');
            $user = auth()->user();

            // Determinar el scope del mensaje
            $scope = $context['scope'] ?? 'tutor';

            // Construir prompts según el scope
            if ($scope === 'dashboard') {
                // === SCOPE DASHBOARD: Conocimiento completo ===
                $isNewUser = $context['is_new_user'] ?? false;
                $hasActivity = ($context['total_games_played'] ?? 0) > 0 || ($context['total_exercises'] ?? 0) > 0 || ($context['total_conversations'] ?? 0) > 0;

                $systemPrompt = "Eres 'Cirilo', el asistente IA personal del usuario. Eres amigable, motivador y conoces TODA la actividad del usuario.\n";
                $systemPrompt .= "Tu objetivo es saludar al usuario demostrando que conoces su actividad reciente en TODAS las áreas.\n\n";
                $systemPrompt .= "REGLAS IMPORTANTES:\n";
                $systemPrompt .= "1. Sé breve y natural (2-3 frases máximo).\n";
                $systemPrompt .= "2. Menciona algo específico que el usuario haya hecho recientemente.\n";
                $systemPrompt .= "3. Si el usuario lleva días sin entrar, menciónalo de forma motivadora.\n";
                $systemPrompt .= "4. Recomienda una actividad específica (puede ser juegos, tutor, o el asistente).\n";
                $systemPrompt .= "5. Si es usuario NUEVO, dale una cálida bienvenida y explica las funciones disponibles.\n";
                $systemPrompt .= "6. Usa el nombre del usuario.\n";
                $systemPrompt .= "7. Responde SIEMPRE en español.\n";
                $systemPrompt .= "8. Máximo 1-2 emojis.\n\n";

                if ($isNewUser) {
                    $systemPrompt .= "CONTEXTO ESPECIAL: Usuario NUEVO. Bienvenida especial.\n";
                } elseif (! $hasActivity) {
                    $systemPrompt .= "CONTEXTO ESPECIAL: Sin actividad. Anímalo a explorar.\n";
                }

                $userPrompt = "Genera un mensaje de bienvenida personalizado:\n\n";
                $userPrompt .= "=== DATOS BÁSICOS ===\n";
                $userPrompt .= '- Nombre: '.($context['name'] ?? 'Estudiante')."\n";
                $userPrompt .= '- Edad: '.($context['age'] ?? 'No especificada')."\n";
                $userPrompt .= '- Días desde último login: '.($context['days_since_login'] ?? 0)."\n\n";

                $userPrompt .= "=== ASISTENTE VIRTUAL ===\n";
                $userPrompt .= '- Conversaciones: '.($context['total_conversations'] ?? 0)."\n";
                if ($context['last_conversation_topic'] ?? null) {
                    $userPrompt .= '- Última: "'.$context['last_conversation_topic'].'" ('.$context['last_conversation_date'].")\n";
                }

                $userPrompt .= "\n=== JUEGOS ===\n";
                $userPrompt .= '- Partidas: '.($context['total_games_played'] ?? 0)."\n";
                if ($context['last_game_type'] ?? null) {
                    $userPrompt .= '- Último: '.$context['last_game_type'].' (puntuación: '.$context['last_game_score'].') - '.$context['last_game_date']."\n";
                }

                $userPrompt .= "\n=== TUTOR INGLÉS ===\n";
                $userPrompt .= '- Nivel: '.($context['english_level'] ?? 'Sin evaluar')."\n";
                $userPrompt .= '- Ejercicios completados: '.($context['total_exercises'] ?? 0)."\n";
                $userPrompt .= '- Cursos: '.($context['custom_courses_count'] ?? 0)."\n";

            } else {
                // === SCOPE TUTOR: Solo temas de inglés/tutor ===
                $systemPrompt = "Eres 'Cirilo', el tutor de inglés IA personal del usuario. Eres experto en aprendizaje de idiomas.\n";
                $systemPrompt .= "Tu objetivo es motivar al usuario a continuar su aprendizaje de INGLÉS.\n\n";
                $systemPrompt .= "REGLAS IMPORTANTES:\n";
                $systemPrompt .= "1. Sé breve y natural (2-3 frases máximo).\n";
                $systemPrompt .= "2. Solo habla de temas relacionados con el aprendizaje de inglés.\n";
                $systemPrompt .= "3. Si el usuario tiene progreso, menciona su nivel y felicítalo.\n";
                $systemPrompt .= "4. Si tiene área débil, sugiere practicarla (vocabulario, gramática, speaking, listening).\n";
                $systemPrompt .= "5. Si no tiene progreso, anímalo a hacer la evaluación de nivel.\n";
                $systemPrompt .= "6. Usa el nombre del usuario.\n";
                $systemPrompt .= "7. Responde SIEMPRE en español.\n";
                $systemPrompt .= "8. Máximo 1-2 emojis.\n";

                $userPrompt = "Genera un mensaje para el tutor de inglés:\n\n";
                $userPrompt .= '- Nombre: '.($context['name'] ?? 'Estudiante')."\n";
                $userPrompt .= '- Nivel de inglés: '.($context['english_level'] ?? 'Sin evaluar')."\n";
                $userPrompt .= '- Puntuaciones: Vocabulario '.($context['vocabulary_score'] ?? 0).'%, Gramática '.($context['grammar_score'] ?? 0).'%, Speaking '.($context['speaking_score'] ?? 0).'%, Listening '.($context['listening_score'] ?? 0)."%\n";
                $userPrompt .= '- Área más débil: '.($context['weakest_skill'] ?? 'No determinada')."\n";
                $userPrompt .= '- Área más fuerte: '.($context['strongest_skill'] ?? 'No determinada')."\n";
                $userPrompt .= '- Ejercicios completados: '.($context['total_exercises'] ?? 0)."\n";
                if ($context['last_exercise_type'] ?? null) {
                    $userPrompt .= '- Último ejercicio: '.$context['last_exercise_type'].' (puntuación: '.$context['last_exercise_score'].') - '.$context['last_exercise_date']."\n";
                }
                $userPrompt .= '- Cursos personalizados: '.($context['custom_courses_count'] ?? 0)."\n";
                $userPrompt .= '- Cursos completados: '.($context['courses_completed'] ?? 0)."\n";
                $userPrompt .= '- Días sin actividad en el tutor: '.($context['days_inactive'] ?? 0)."\n";
            }

            $credentials = $this->getApiCredentials('openai');

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer '.$credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->post($credentials['base_url'].'/chat/completions', [
                'model' => 'gpt-4.1',
                'messages' => $messages,
                'max_tokens' => 150,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $message = $responseData['choices'][0]['message']['content'];

                // Generar audio automáticamente
                $audioRequest = new Request(['text' => $message]);
                $audioResponse = $this->textToSpeech($audioRequest);
                $audioData = json_decode($audioResponse->getContent(), true);
                $audioUrl = $audioData['audioUrl'] ?? null;

                return response()->json([
                    'message' => $message,
                    'audioUrl' => $audioUrl,
                    'success' => true,
                ]);
            } else {
                throw new \Exception('Error al generar mensaje del tutor.');
            }

        } catch (\Exception $e) {
            Log::error('Error en generateTutorWelcome: '.$e->getMessage());

            return response()->json([
                'message' => '¡Hola! Bienvenido al Centro de Aprendizaje. Estoy aquí para ayudarte a mejorar tu inglés. ¡Empecemos!',
                'audioUrl' => null,
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function switchProvider(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:openai,grok',
        ]);

        $user = auth()->user();
        $user->ai_provider = $request->provider;
        $user->save();

        return response()->json([
            'message' => 'Proveedor de IA cambiado exitosamente a '.$request->provider,
            'provider' => $request->provider,
        ]);
    }

    /**
     * Detecta si el usuario quiere GENERAR una imagen (DALL-E).
     */
    private function detectImageGenerationIntent(string $prompt): bool
    {
        $lower = mb_strtolower($prompt);

        // Requiere mención explícita de "imagen", "foto", "dibujo", "ilustración"
        // junto a un verbo de creación. Evita falsos positivos como
        // "qué hay en esta imagen" (que es análisis, no generación).
        $hasImageNoun = false;
        foreach (['imagen', 'imágen', 'imagenes', 'imágenes', 'foto', 'fotografía', 'fotografia',
                  'dibujo', 'ilustración', 'ilustracion', 'render', 'pintura', 'retrato', 'logo'] as $n) {
            if (str_contains($lower, $n)) {
                $hasImageNoun = true;
                break;
            }
        }

        if (! $hasImageNoun) {
            // Verbos que implican imagen sin necesidad de mencionarla
            foreach (['dibújame', 'dibujame', 'dibújalo', 'dibujalo', 'dibújala', 'dibujala',
                      'pintame', 'píntame', 'ilustra', 'ilústrame', 'ilustrame'] as $v) {
                if (str_contains($lower, $v)) {
                    return true;
                }
            }
            return false;
        }

        // Verbos de creación + sustantivo de imagen ya detectado
        $createKeywords = [
            'genera', 'genérame', 'generame', 'generala', 'genérala', 'générame',
            'crea', 'créame', 'creame', 'créala', 'creala', 'créalo', 'crealo',
            'haz', 'hazme', 'hazla', 'hazlo',
            'dibuja', 'dibújame', 'dibujame',
            'diseña', 'diséñame', 'disename',
            'ilustra', 'ilústrame', 'ilustrame',
            'muéstrame', 'muestrame',
            'imagina', 'imagíname', 'imaginame',
            'quiero una', 'quiero un', 'quisiera una', 'quisiera un',
            'necesito una', 'necesito un',
            'me puedes generar', 'me puedes crear', 'me puedes dibujar', 'me puedes hacer',
            'puedes generar', 'puedes crear', 'puedes dibujar', 'puedes hacer',
            'genera una', 'genera un', 'crea una', 'crea un',
            'hazme una', 'hazme un', 'haz una', 'haz un',
        ];

        foreach ($createKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Heurística: ¿el mensaje del asistente está pidiendo los datos de un evento?
     * Se usa solo en móvil para reconstruir el flujo multi-turno sin sesión: si el
     * último mensaje de Cirilo pedía título/fecha/hora, el siguiente turno del
     * usuario se trata como continuación de la creación del evento.
     */
    private function looksLikeEventDetailRequest(string $text): bool
    {
        if (! str_contains($text, '?')) {
            return false;
        }

        return (bool) preg_match(
            '/t[íi]tulo|nombre|fecha|hora|cu[áa]ndo|qu[ée] evento|c[óo]mo se llama|detalles/i',
            $text
        );
    }

    /**
     * Detecta si el usuario quiere CREAR un evento en el calendario.
     */
    private function detectCalendarCreateIntent(string $prompt): bool
    {
        $lower = mb_strtolower($prompt);
        $createKeywords = [
            // Imperativo directo
            'agéndame', 'agendame', 'agendala', 'agéndala', 'agendalo', 'agéndalo',
            'agrégame', 'agregame', 'agrégala', 'agrégalo',
            'apúntame', 'apuntame', 'apúntala', 'apúntalo',
            'anótame', 'anotame', 'anótalo', 'anotalo', 'anótala', 'anotala',
            'guárdala', 'guardala', 'guárdalo', 'guardalo',
            'prográmame', 'programame',
            'ponla en', 'ponlo en',
            // Recordatorio implica creación de evento
            'ponme un recordatorio', 'pon un recordatorio', 'ponme recordatorio',
            // Frases de deseo/necesidad
            'quiero agendar', 'quiero que agendes', 'quiero que crees', 'quiero que lo agendes', 'quiero que la agendes',
            'quisiera agendar', 'quisiera que agendes', 'quisiera que programes', 'quisiera que me avises',
            'necesito agendar', 'necesito que agendes',
            'me gustaría agendar', 'me gustaria agendar', 'me gustaría que agendes',
            'tengo que agendar',
            'ayúdame a agendar', 'ayudame a agendar',
            // Frases de creación explícita
            'crea un evento', 'crea el evento', 'crear un evento',
            'crea una cita', 'crear una cita',
            'crea una reunión', 'crear una reunión',
            'programa un', 'programa el', 'programar un',
            'programa una reunión', 'programa una cita',
            'puedes agendar', 'puedes crear', 'puedes programar',
            'pon en mi agenda', 'añade a mi agenda', 'agrega a mi agenda',
            'agenda una', 'agenda un', 'agenda el', 'agenda para',
            'registra un evento', 'registra la reunión', 'registra el evento',
        ];

        foreach ($createKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida que los datos extraídos de un evento sean suficientes para crearlo.
     */
    private function isEventDataComplete(array $data): bool
    {
        $title = trim($data['title'] ?? '');
        $placeholders = ['string', 'null', 'none', 'n/a', 'evento', 'evento sin título', ''];

        if (empty($title) || in_array(strtolower($title), $placeholders)) {
            return false;
        }

        // Fecha mínima requerida
        if (empty($data['start_date'])) {
            return false;
        }

        return true;
    }

    /**
     * Extrae datos estructurados de un evento a partir del prompt del usuario usando GPT.
     */
    private function extractEventDataFromPrompt(string $prompt, string $apiKey): ?array
    {
        $tz = 'America/Guatemala';
        $now = \Carbon\Carbon::now($tz)->format('Y-m-d H:i');

        $extractionPrompt = "Analiza el siguiente texto y extrae los datos de un evento de calendario. "
            ."Responde SOLO con JSON válido, sin texto adicional ni bloques de código.\n\n"
            ."Fecha y hora actual: {$now} (zona horaria: Guatemala CST, UTC-6)\n\n"
            ."Texto:\n\"{$prompt}\"\n\n"
            ."Devuelve un JSON con esta estructura (usa null para campos no mencionados):\n"
            .'{"title":null,"start_date":null,"start_time":null,"end_date":null,"end_time":null,"description":null,"location":null,"reminder_minutes_before":30,"recurrence_type":"none","recurrence_days":null,"recurrence_end_date":null}'."\n\n"
            ."Reglas importantes:\n"
            ."- title: nombre concreto del evento. Si el usuario NO mencionó un nombre específico, devuelve null\n"
            ."- start_date: formato YYYY-MM-DD. Si dice 'hoy' o no se menciona ninguna fecha, usa la fecha actual. Si dice 'mañana' calcula la fecha de mañana. Si dice un día de la semana (ej. 'el lunes'), calcula la fecha del próximo lunes.\n"
            ."- start_time: formato HH:MM en 24h. Si no se menciona hora, devuelve null\n"
            ."- end_time: si no se menciona, añade 1 hora a start_time. Si start_time es null, devuelve null\n"
            ."- end_date: igual a start_date si no se menciona otra fecha\n"
            ."- reminder_minutes_before: extráelo si el usuario lo menciona, sino usa 30\n"
            ."- recurrence_type: 'none' si es evento único; 'daily' si es todos los días; 'weekdays' si es lunes a viernes; 'weekly' si repite cada semana en días específicos; 'custom' para otros patrones de días específicos\n"
            ."- recurrence_days: array de días en inglés en minúsculas (monday/tuesday/wednesday/thursday/friday/saturday/sunday). Solo si recurrence_type NO es 'none' ni 'daily'. Para 'weekdays' devuelve [\"monday\",\"tuesday\",\"wednesday\",\"thursday\",\"friday\"]\n"
            ."- recurrence_end_date: fecha fin de la recurrencia en YYYY-MM-DD si el usuario la menciona explícitamente, sino null";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1-mini',
                'messages' => [['role' => 'user', 'content' => $extractionPrompt]],
                'temperature' => 0,
                'max_tokens' => 400,
            ]);

            if (! $response->successful()) {
                Log::error('Error al extraer datos del evento: '.$response->body());
                return null;
            }

            $content = $response->json('choices.0.message.content');
            // Limpiar posibles bloques markdown ```json ... ```
            $content = preg_replace('/^```json\s*/m', '', $content);
            $content = preg_replace('/^```\s*/m', '', $content);
            $eventData = json_decode(trim($content), true);

            return is_array($eventData) ? $eventData : null;
        } catch (\Exception $e) {
            Log::error('Excepción al extraer datos del evento: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Crea uno o varios CalendarEvent en la base de datos a partir de datos extraídos por IA.
     * Soporta recurrencia: daily, weekdays (lun-vie), weekly y custom (días específicos).
     * Retorna array con ['event' => primer_evento, 'count' => total, 'summary' => descripción].
     */
    private function createEventFromChat(array $data, int $userId): array
    {
        $tz            = 'America/Guatemala';
        $startTime     = $data['start_time'] ?? '09:00';
        $endTime       = $data['end_time']   ?? '10:00';
        $recurrenceType     = $data['recurrence_type'] ?? 'none';
        $recurrenceDays     = $data['recurrence_days'] ?? null;
        $recurrenceEndDate  = $data['recurrence_end_date'] ?? null;

        // series_id compartido — se asigna antes del loop para series recurrentes
        $seriesId = null;

        // Closure para crear un evento en una fecha concreta
        $makeEvent = function (string $date) use ($data, $userId, $tz, $startTime, $endTime, &$seriesId): \App\Models\CalendarEvent {
            $event = new \App\Models\CalendarEvent;
            $event->user_id                 = $userId;
            $event->series_id               = $seriesId;
            $event->title                   = $data['title'] ?? 'Evento sin título';
            $event->description             = $data['description'] ?? '';
            $event->start_date              = \Carbon\Carbon::parse("{$date} {$startTime}", $tz);
            $event->end_date                = \Carbon\Carbon::parse("{$date} {$endTime}", $tz);
            $event->all_day                 = false;
            $event->category                = 'general';
            $event->location                = $data['location'] ?? '';
            $event->color                   = '#3788d8';
            $event->reminder_minutes_before = (int) ($data['reminder_minutes_before'] ?? 30);
            $event->notified                = false;
            $event->status                  = 'pending';
            $event->save();
            return $event;
        };

        try {
            // ── Evento único ──────────────────────────────────────────────
            if ($recurrenceType === 'none' || $recurrenceType === null) {
                $event = $makeEvent($data['start_date'] ?? date('Y-m-d'));
                Log::info("Evento único creado desde chat para usuario {$userId}: {$event->title} ({$event->start_date})");
                return ['event' => $event, 'count' => 1, 'summary' => null];
            }

            // ── Mapeo de nombres de día → dayOfWeek de Carbon (0=Dom … 6=Sáb) ──
            $dayMap = [
                'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0,
                'lunes' => 1, 'martes' => 2, 'miércoles' => 3, 'miercoles' => 3,
                'jueves' => 4, 'viernes' => 5, 'sábado' => 6, 'sabado' => 6, 'domingo' => 0,
            ];

            // ── Determinar días objetivo ──────────────────────────────────
            if ($recurrenceType === 'daily') {
                $targetDays = [0, 1, 2, 3, 4, 5, 6];
            } elseif ($recurrenceType === 'weekdays') {
                $targetDays = [1, 2, 3, 4, 5];
            } else {
                // weekly / custom — usar recurrence_days
                $targetDays = array_values(array_unique(array_filter(
                    array_map(fn ($d) => $dayMap[strtolower(trim($d))] ?? null, (array) $recurrenceDays),
                    fn ($d) => $d !== null
                )));
            }

            // Si no hay días válidos, crear evento único
            if (empty($targetDays)) {
                $event = $makeEvent($data['start_date'] ?? date('Y-m-d'));
                return ['event' => $event, 'count' => 1, 'summary' => null];
            }

            // ── Rango de fechas (máximo 90 días) ──────────────────────────
            $startDate = \Carbon\Carbon::parse($data['start_date'] ?? 'today', $tz)->startOfDay();
            $endDate   = $recurrenceEndDate
                ? \Carbon\Carbon::parse($recurrenceEndDate, $tz)->startOfDay()
                : $startDate->copy()->addWeeks(4);

            $maxEnd = $startDate->copy()->addDays(90);
            if ($endDate->gt($maxEnd)) {
                $endDate = $maxEnd;
            }

            // ── Asignar series_id antes de crear los eventos ──────────────
            $seriesId = \Illuminate\Support\Str::uuid()->toString();

            // ── Crear un evento por cada día que coincida ─────────────────
            $events  = [];
            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                if (in_array($current->dayOfWeek, $targetDays)) {
                    $events[] = $makeEvent($current->format('Y-m-d'));
                }
                $current->addDay();
            }

            if (empty($events)) {
                $event = $makeEvent($data['start_date'] ?? date('Y-m-d'));
                return ['event' => $event, 'count' => 1, 'summary' => null];
            }

            $labelMap = [
                'daily'    => 'todos los días',
                'weekdays' => 'lunes a viernes',
            ];
            $recurrenceLabel = $labelMap[$recurrenceType]
                ?? implode(', ', array_map('ucfirst', (array) $recurrenceDays));
            $endFmt  = $endDate->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
            $summary = "{$recurrenceLabel} hasta el {$endFmt}";

            Log::info("Eventos recurrentes creados desde chat para usuario {$userId}: {$events[0]->title} × ".count($events)." ({$summary})");

            return ['event' => $events[0], 'count' => count($events), 'summary' => $summary];

        } catch (\Exception $e) {
            Log::error('Error al crear evento desde chat: '.$e->getMessage());
            return ['event' => null, 'count' => 0, 'summary' => null];
        }
    }

    /**
     * Detecta si el prompt es sobre el calendario e inyecta los eventos del usuario como contexto.
     */
    private function getCalendarContext($user, string $prompt): string
    {
        if (! $user) {
            return '';
        }

        $lower = mb_strtolower($prompt);

        $keywords = [
            'agenda', 'evento', 'eventos', 'cita', 'citas', 'reunión', 'reuniones',
            'recordatorio', 'recordatorios', 'calendario', 'programado', 'programada',
            'hoy', 'mañana', 'semana', 'próximo', 'próxima', 'próximos', 'próximas',
            'qué tengo', 'que tengo', 'tengo algo', 'tengo pendiente',
        ];

        $isCalendarQuery = false;
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                $isCalendarQuery = true;
                break;
            }
        }

        if (! $isCalendarQuery) {
            return '';
        }

        $tz = 'America/Guatemala';
        $now = \Carbon\Carbon::now($tz);

        if (str_contains($lower, 'mañana')) {
            $start = $now->copy()->addDay()->startOfDay();
            $end = $now->copy()->addDay()->endOfDay();
            $period = 'mañana';
        } elseif (str_contains($lower, 'semana')) {
            $start = $now->copy()->startOfWeek();
            $end = $now->copy()->endOfWeek();
            $period = 'esta semana';
        } elseif (str_contains($lower, 'hoy')) {
            $start = $now->copy()->startOfDay();
            $end = $now->copy()->endOfDay();
            $period = 'hoy';
        } else {
            $start = $now->copy()->startOfDay();
            $end = $now->copy()->addDays(30)->endOfDay();
            $period = 'los próximos 30 días';
        }

        $events = \App\Models\CalendarEvent::where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->orderBy('start_date')
            ->limit(20)
            ->get();

        if ($events->isEmpty()) {
            return "\n### Agenda del Usuario:\nNo tienes eventos agendados para {$period}.\n\n";
        }

        $context = "\n### Agenda del Usuario ({$period}):\n";
        foreach ($events as $event) {
            $startDt = \Carbon\Carbon::parse($event->start_date)->setTimezone($tz);
            $dateStr = $startDt->locale('es')->isoFormat('dddd D [de] MMMM');
            $timeStr = $event->all_day ? 'Todo el día' : $startDt->format('H:i');
            $context .= "- **{$event->title}** — {$dateStr} a las {$timeStr}";
            if ($event->location) {
                $context .= " (Ubicación: {$event->location})";
            }
            if ($event->description) {
                $context .= " — {$event->description}";
            }
            $context .= "\n";
        }
        $context .= "\n";

        return $context;
    }
}
