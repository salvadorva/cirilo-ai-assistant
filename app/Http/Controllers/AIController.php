<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\Agenda\AgendaDateRange;
use App\Services\Agenda\AgendaIntent;
use App\Services\Agenda\AgendaResult;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\AgendaTools;
use App\Services\Agenda\ChatAgenda;
use App\Services\ChatContext;
use App\Services\Tasks\TaskIntent;
use App\Services\Tasks\TaskService;
use App\Services\ExplicitMemoryService;
use App\Services\MemoryService;
use App\Services\ImageQuotaService;
use App\Support\ChatInput;
use App\Traits\LogsApiUsage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Support\AiLog as Log;
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

    private function httpClient(array $config = []): Client
    {
        // Container resolution allows tests to block all provider traffic.
        return app(\App\Services\AiTransport::class)->client($config);
    }

    public function generateText(Request $request)
    {
        ChatInput::validate($request);
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
                        'model' => config('ai.models.tts'),
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

        // F4-02: historial con presupuesto; sin historial del cliente se usan los mensajes guardados.
        $activeConversation = ($user && $currentConversationId)
            ? Conversation::where('user_id', $user->id)->find($currentConversationId)
            : null;
        $history = ChatContext::history(is_array($history) ? $history : [], $activeConversation, (string) $prompt);

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
            // F4-08: «Llámame X» se guarda ya, sin esperar al resumen, y entra en este mismo turno.
            try {
                ExplicitMemoryService::capture($user->id, $prompt, $activeConversation?->id);
            } catch (\Throwable $e) {
                Log::warning('ExplicitMemoryService: no se pudo guardar la preferencia', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }

            $contextBlock = app(\App\Services\InteractionTracker::class)->measure('context', fn () => MemoryService::buildContextBlock($user->id, $currentConversationId, (string) $prompt));
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
            } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::error('[AIController] Error generando imagen desde chat: '.$e->getMessage());
            }
        }

        // F2: agenda desde el chat — intención, borrador por conversación, idempotencia y persistencia.
        $agendaOutcome = ['status' => ChatAgenda::NONE, 'result' => null, 'missing' => [], 'message' => null];
        $idempotencyKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($idempotencyKey) && $idempotencyKey !== '' ? mb_substr($idempotencyKey, 0, 100) : null;
        // F2-02: con el flag, OpenAI gestiona la agenda con herramientas; Grok sigue con el flujo determinista (F2-08).
        $agendaTools = $user && $provider === 'openai' && config('ai.agenda_tools.enabled')
            ? new AgendaTools(app(AgendaService::class), $user, $idempotencyKey) : null;
        if ($user && ! $agendaTools) {
            $agendaOutcome = app(ChatAgenda::class)->handle(
                $user, (string) $prompt, $history,
                $activeConversation?->id,
                $request->hasSession() ? $request->session()->getId() : null,
                $idempotencyKey,
            );
        }
        // F6-03 (sin herramientas): «guarda como pendiente…» o «tengo que…, sin fecha» → pendiente sin horario.
        $taskOutcome = ['status' => 'none', 'tasks' => []];
        if ($user && ! $agendaTools && $agendaOutcome['status'] === ChatAgenda::NONE && ($taskTitle = TaskIntent::capture((string) $prompt))) {
            try {
                [$taskStatus, $task] = app(TaskService::class)->create($user, $taskTitle, null, 'chat', $activeConversation?->id);
                $taskOutcome = ['status' => $taskStatus, 'tasks' => [$task]];
            } catch (\Throwable $e) {
                Log::error('No se pudo guardar el pendiente: '.$e->getMessage());
                $taskOutcome = ['status' => 'failed', 'tasks' => []];
            }
        }
        $createdEvent = in_array($agendaOutcome['status'], [AgendaResult::CREATED, AgendaResult::REPLAYED], true) ? $agendaOutcome['result']->first() : null;
        $createdCount = $createdEvent ? count($agendaOutcome['result']->events) : 0;

        // Inyectar contexto del calendario si la consulta es relevante
        $calendarContext = app(\App\Services\InteractionTracker::class)->measure('agenda_context', fn () => $this->getCalendarContext($user, $prompt));
        if ($calendarContext) {
            $systemPrompt .= $calendarContext;
        }

        $agendaInstructions = ($agendaTools ? AgendaTools::instructions() : ChatAgenda::instructions($agendaOutcome)).self::taskInstructions($taskOutcome);
        if ($agendaInstructions !== '') {
            $systemPrompt .= $agendaInstructions;
        } elseif ($calendarContext) {
            $systemPrompt .= "Si el usuario quiere **crear** un nuevo evento, puedes hacerlo directamente: solo pídele los detalles necesarios.\n";
        }

        try {
            $credentials = $this->getApiCredentials($provider);
            $client = $this->httpClient();

            Log::info('Text generation', ['provider' => $provider, 'count' => count($history)]);

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
                        'model' => config('ai.models.chat'),
                        'instructions' => $systemPrompt,
                        'input' => $inputMessages,
                        'tools' => array_merge([['type' => 'web_search_preview', 'search_context_size' => config('ai.web_search.context_size', 'medium')]], $agendaTools ? AgendaTools::definitions() : []),
                        'temperature' => 0.6,
                    ],
                ];
                $endpoint = $credentials['base_url'].'/responses';
            } elseif ($provider === 'grok') {
                // $requestData['json']['model'] = 'grok-4-fast-reasoning';
                $requestData['json']['model'] = config('ai.models.grok');
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
                $client = $this->httpClient([
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

            $ctx = compact('provider', 'requestData', 'prompt', 'agendaTools', 'agendaOutcome', 'taskOutcome', 'createdEvent', 'createdCount',
                'generateAudio', 'voice', 'systemPrompt', 'messages', 'generatedImage');

            // F5-03: streaming opcional (Accept: text/event-stream); los clientes JSON no cambian.
            if ($provider === 'openai' && $this->wantsStream($request)) {
                return $this->streamTextResponse($client, $endpoint, $requestData, $ctx);
            }

            // Realizar la solicitud
            $startTime = microtime(true);
            $response = $client->post($endpoint, $requestData);
            $data = json_decode($response->getBody(), true) ?? [];

            // F2-02: ciclo llamada → validación → ejecución → resultado → respuesta, acotado.
            if ($agendaTools) {
                $data = $this->runAgendaTools($client, $endpoint, $requestData, $data, $agendaTools);
            }
            $ctx['responseTime'] = (int) ((microtime(true) - $startTime) * 1000);

            Log::info('Text response', [
                'provider' => $provider, 'model' => $requestData['json']['model'],
                'response_time_ms' => $ctx['responseTime'], 'status_code' => $response->getStatusCode(),
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
            ]);

            return response()->json($this->completeTextResponse($data, $ctx));
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
                        $openaiClient = $this->httpClient([
                            'timeout' => 60,
                            'http_errors' => false,
                        ]);

                        $openaiResponse = $openaiClient->post($openaiCredentials['base_url'].'/chat/completions', [
                            'headers' => [
                                'Authorization' => 'Bearer '.$openaiCredentials['api_key'],
                                'Content-Type' => 'application/json',
                            ],
                            'json' => [
                                // 'model' => config('ai.models.vision'),
                                'model' => config('ai.models.chat'),
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
                    'error' => 'El proveedor no pudo completar la solicitud.',
                    'provider' => $provider,
                    'errorCode' => $statusCode,
                ], $statusCode);
            }

            return response()->json([
                'error' => 'El proveedor no pudo completar la solicitud. Intenta de nuevo más tarde.',
                'provider' => $provider,
            ], 500);
        }
    }

    public function generateImage(Request $request)
    {
        abort_unless($request->user(), 401);
        $request->validate(['prompt' => 'required|string|max:'.config('ai_security.prompt_length')]);

        return app(ImageQuotaService::class)->generate($request->user(), fn () => $this->requestImage($request));
    }

    private function requestImage(Request $request)
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
            $client = $this->httpClient([
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
                    'model' => config('ai.models.image'),
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

                return response()->json([
                    'image_url' => $imageUrl,
                    'provider_used' => $provider,
                ]);
            } else {
                $errorMessage = 'Error al generar la imagen.';

                if (($responseData['error']['type'] ?? '') === 'image_generation_user_error') {
                    $errorMessage = 'OpenAI no puede generar esta imagen. Intenta con un prompt diferente.';
                }

                Log::error('Error en la respuesta de '.$provider.': '.$statusCode.' - '.$errorMessage);

                $responseTime = (int) ((microtime(true) - $startTime) * 1000);
                return response()->json([
                    'error' => $errorMessage,
                    'provider' => $provider,
                ], $statusCode >= 400 ? $statusCode : 502);
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
                'error' => 'El proveedor no pudo generar la imagen. Intenta de nuevo más tarde.',
                'provider' => $provider,
            ], 500);
        } catch (\Exception $e) {
            // Cualquier otro error
            Log::error('Excepción: '.$e->getMessage());

            return response()->json([
                'error' => 'Ocurrió un error al generar la imagen.',
                'provider' => $provider,
            ], 500);
        }
    }

    public function textToSpeech(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:'.config('ai_security.tts_length'),
            'voice' => 'sometimes|in:alloy,echo,fable,nova,onyx,shimmer',
        ]);
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

            Log::info('TTS request', ['provider' => 'openai', 'text_length' => mb_strlen($text)]);

            $startTime = microtime(true);

            // Aumentar el timeout a 120 segundos y agregar reintentos
            $response = Http::timeout(120)
                ->retry(3, 100, throw: false) // Devolver también el estado del fallo sin exponer excepciones.
                ->withHeaders([
                    'Authorization' => 'Bearer '.$credentials['api_key'],
                    'Content-Type' => 'application/json',
                ])->post($credentials['base_url'].'/audio/speech', [
                    'model' => config('ai.models.tts'),
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
                $errorMessage = 'No se pudo generar el audio. Intenta de nuevo más tarde.';

                return response()->json([
                    'error' => $errorMessage,
                    'provider' => 'openai',
                    'status_code' => $statusCode,
                ], $statusCode);
            }
        } catch (\Exception $e) {
            Log::error('Excepción en text-to-speech: '.$e->getMessage()."\n".$e->getTraceAsString());

            return response()->json([
                'error' => 'No se pudo generar el audio. Intenta de nuevo más tarde.',
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
            $client = $this->httpClient();
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
                                'contents' => config('ai.models.stt'),
                            ],
                            [
                                'name' => 'response_format',
                                'contents' => 'verbose_json',
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
                'error' => 'No se pudo procesar el audio. Intenta de nuevo más tarde.',
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
                'model' => config('ai.models.chat'),
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
                'error' => 'No se pudo generar el resumen.',
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
                'model' => config('ai.models.chat'),
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
                'error' => 'No se pudo generar el saludo.',
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
    /**
     * Posprocesamiento común (JSON y streaming): texto, uso, audio opcional, tokens, agenda e imagen.
     */
    private function completeTextResponse(array $data, array $ctx): array
    {
        extract($ctx);

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

            if ($agendaTools) {
                $taskOutcome = $agendaTools->taskOutcome();
                $agendaOutcome = $agendaTools->outcome((string) $prompt, $responseText);
                $persisted = in_array($agendaOutcome['status'], [AgendaResult::CREATED, AgendaResult::REPLAYED], true) ? $agendaOutcome['events'] : [];
                $createdEvent = $persisted[0] ?? null;
                $createdCount = count($persisted);
            }
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
                    'model' => config('ai.models.tts'),
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

        // F2-06: contrato común de agenda (web y móvil); calendar_event_created se conserva por compatibilidad.
        $data['agenda'] = ChatAgenda::contract($agendaOutcome);

        // F6-06: resultado de pendientes, visible y corregible desde la tarjeta o desde /hoy.
        $data['tasks'] = ['status' => $taskOutcome['status'], 'items' => array_map(fn ($t) => TaskService::describe($t), $taskOutcome['tasks'])];
        if ($taskOutcome['status'] === 'created' && $taskOutcome['tasks']) {
            $data['task_created'] = TaskService::describe($taskOutcome['tasks'][0]);
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


        return $data;
    }

    private function wantsStream(Request $request): bool
    {
        return str_contains((string) $request->header('Accept'), 'text/event-stream') || $request->boolean('stream');
    }

    /**
     * F5-03/F5-05: respuesta en Server-Sent Events. `delta` con texto parcial, `agenda` en cuanto una
     * acción queda guardada (así el cliente puede avisar aunque el usuario detenga la respuesta),
     * `done` con el mismo contrato que la respuesta JSON, o `error`.
     */
    private function streamTextResponse(Client $client, string $endpoint, array $requestData, array $ctx)
    {
        $interactionId = app(\App\Services\InteractionTracker::class)->id;

        return response()->stream(function () use ($client, $endpoint, $requestData, $ctx, $interactionId) {
            ignore_user_abort(true); // Terminar el registro aunque el usuario cierre la conexión.
            $tracker = app(\App\Services\InteractionTracker::class);
            $tracker->id = $interactionId;
            $emit = function (string $event, array $payload): bool {
                echo "event: {$event}\ndata: ".json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0 && ! app()->runningUnitTests()) {
                    @ob_flush();
                }
                flush();

                return ! connection_aborted();
            };
            $agendaTools = $ctx['agendaTools'];

            try {
                if (! $agendaTools && in_array($ctx['agendaOutcome']['status'], [AgendaResult::CREATED, AgendaResult::REPLAYED], true)) {
                    $emit('agenda', ChatAgenda::contract($ctx['agendaOutcome']));
                }
                $start = microtime(true);
                $data = $this->streamProvider($client, $endpoint, $requestData, fn (string $delta) => $emit('delta', ['text' => $delta]));
                $aborted = connection_aborted() || ! empty($data['aborted']);
                $calls = array_filter($data['output'] ?? [], fn ($item) => ($item['type'] ?? '') === 'function_call');
                if ($agendaTools && $calls && ! $aborted) {
                    $emit('status', ['stage' => 'agenda']);
                    $data = $this->runAgendaTools($client, $endpoint, $requestData, $data, $agendaTools);
                    $emit('agenda', ChatAgenda::contract($agendaTools->outcome((string) $ctx['prompt'], '')));
                }
                $ctx['responseTime'] = (int) ((microtime(true) - $start) * 1000);
                if ($aborted) {
                    $ctx['generateAudio'] = false; // El usuario detuvo la respuesta: no generar audio.
                }
                $emit('done', $this->completeTextResponse($data, $ctx) + ['interaction_id' => $interactionId]);
            } catch (\Throwable $e) {
                Log::error('Chat en streaming falló: '.$e->getMessage());
                $emit('error', ['message' => 'Lo siento, ocurrió un error al generar la respuesta. Intenta de nuevo.']);
            } finally {
                $tracker->id = null;
            }
        }, 200, ['Content-Type' => 'text/event-stream; charset=UTF-8', 'Cache-Control' => 'no-cache, no-transform', 'X-Accel-Buffering' => 'no']);
    }

    /**
     * Llama a la Responses API con stream=true y reenvía cada fragmento de texto. Devuelve la respuesta
     * final (evento response.completed) con output y uso. Si $onDelta devuelve false, deja de leer.
     */
    private function streamProvider(Client $client, string $endpoint, array $requestData, callable $onDelta): array
    {
        $requestData['json']['stream'] = true;
        $requestData['stream'] = true;
        $response = $client->post($endpoint, $requestData);
        $body = $response->getBody();
        $buffer = '';
        $completed = null;

        while (! $body->eof()) {
            $buffer .= str_replace("\r\n", "\n", $body->read(2048));
            while (($position = strpos($buffer, "\n\n")) !== false) {
                $block = substr($buffer, 0, $position);
                $buffer = substr($buffer, $position + 2);
                $payload = null;
                foreach (explode("\n", $block) as $line) {
                    if (str_starts_with($line, 'data: ')) {
                        $payload = json_decode(substr($line, 6), true);
                    }
                }
                $type = is_array($payload) ? ($payload['type'] ?? '') : '';
                if ($type === 'response.output_text.delta') {
                    if ($onDelta((string) ($payload['delta'] ?? '')) === false) {
                        $body->close();
                        app(\App\Services\AiTransport::class)->finishStream($response, []);

                        return ['output' => [], 'aborted' => true];
                    }
                } elseif (in_array($type, ['response.completed', 'response.incomplete'], true)) {
                    $completed = $payload['response'] ?? [];
                } elseif (in_array($type, ['response.failed', 'error'], true)) {
                    throw new \RuntimeException('El proveedor informó un error en el streaming');
                }
            }
        }

        app(\App\Services\AiTransport::class)->finishStream($response, $completed ?? []);

        return $completed ?? ['output' => []];
    }

    /**
     * Ejecuta las llamadas a herramientas de agenda y reenvía los resultados al modelo hasta obtener
     * texto o agotar las iteraciones. Suma el uso de todas las llamadas.
     */
    private function runAgendaTools(Client $client, string $endpoint, array $requestData, ?array $data, AgendaTools $tools): ?array
    {
        $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];
        $input = $requestData['json']['input'];
        for ($iteration = 1; ; $iteration++) {
            foreach (array_keys($usage) as $field) {
                $usage[$field] += (int) ($data['usage'][$field] ?? 0);
            }
            $calls = array_values(array_filter($data['output'] ?? [], fn ($item) => ($item['type'] ?? '') === 'function_call'));
            if ($calls === []) {
                break;
            }
            if ($iteration >= (int) config('ai.agenda_tools.max_iterations', 4)) {
                Log::warning('Agenda: se agotaron las iteraciones de herramientas');
                $data['output'] = [['type' => 'message', 'content' => [['type' => 'output_text',
                    'text' => 'No pude completar la operación de agenda. Inténtalo de nuevo o revisa tu [Agenda](/agenda).']]]];
                break;
            }

            // El modelo necesita ver sus propias llamadas (y razonamientos) junto con cada resultado.
            foreach ($data['output'] as $item) {
                if (in_array($item['type'] ?? '', ['function_call', 'reasoning'], true)) {
                    $input[] = $item;
                }
            }
            foreach ($calls as $call) {
                $input[] = ['type' => 'function_call_output', 'call_id' => $call['call_id'],
                    'output' => json_encode($tools->execute((string) $call['name'], (string) ($call['arguments'] ?? '')), JSON_UNESCAPED_UNICODE)];
            }

            $requestData['json']['input'] = $input;
            $data = json_decode($client->post($endpoint, $requestData)->getBody(), true);
        }
        $data['usage'] = array_merge($data['usage'] ?? [], $usage);

        return $data;
    }

    /** Instrucciones al modelo según lo que realmente pasó con el pendiente (flujo sin herramientas). */
    private static function taskInstructions(array $outcome): string
    {
        $task = $outcome['tasks'][0] ?? null;

        return match ($outcome['status']) {
            'created' => "\n### Pendiente guardado:\nGuardaste el pendiente sin fecha «{$task->title}» en la lista de pendientes del usuario (no es un evento de agenda ni tiene horario). "
                ."Confírmalo en una frase y di que puede verlo, completarlo o posponerlo en [Hoy](/hoy). No le asignes fecha ni hora.\n",
            'duplicate' => "\n### Pendiente ya existente:\n«{$task->title}» ya estaba en la lista de pendientes; no se creó otro. Díselo brevemente.\n",
            'failed' => "\n### Error al guardar el pendiente:\nNO se pudo guardar el pendiente. No digas que quedó guardado; sugiere agregarlo desde [Hoy](/hoy).\n",
            default => '',
        };
    }

    /**
     * Detecta si el usuario quiere CREAR un evento en el calendario (F2-03: AgendaIntent).
     */
    private function detectCalendarCreateIntent(string $prompt): bool
    {
        return AgendaIntent::wantsToCreate($prompt);
    }

    /**
     * Datos suficientes para crear: nombre, fecha y hora (o «todo el día»). Nunca se supone la hora.
     */
    private function isEventDataComplete(array $data): bool
    {
        return app(ChatAgenda::class)->missing($data) === [];
    }

    /**
     * Crea uno o varios eventos a partir de datos del extractor (serie en una sola transacción).
     * Retorna ['event' => primer_evento, 'count' => total, 'summary' => descripción].
     */
    private function createEventFromChat(array $data, int $userId): array
    {
        $result = app(AgendaService::class)->create(\App\Models\User::findOrFail($userId), app(ChatAgenda::class)->attributes($data));

        return $result->persisted()
            ? ['event' => $result->first(), 'count' => count($result->events), 'summary' => $result->recurrenceSummary]
            : ['event' => null, 'count' => 0, 'summary' => null];
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
            'qué tengo', 'que tengo', 'tengo algo', 'tengo pendiente', 'pendiente', 'pendientes', 'tareas', 'por hacer',
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

        $tz = config('app.timezone');
        ['range' => [$start, $end], 'period' => $period] = AgendaDateRange::fromText($prompt, now());
        $events = app(AgendaService::class)->between($user, $start, $end);

        // F6: los pendientes de hoy (los mismos que la vista /hoy); lo completado no aparece.
        $tasks = app(TaskService::class)->forToday($user);
        $tasksBlock = $tasks->isEmpty() ? "\n### Pendientes del usuario:\nNo tiene pendientes abiertos para hoy.\n\n"
            : "\n### Pendientes del usuario (sin horario; no son eventos):\n".$tasks->map(fn ($t) => '- '.$t->title
                .($t->due_date ? ' (fecha límite '.$t->due_date->format('d/m').')' : ''))->implode("\n")."\n\n";

        if ($events->isEmpty()) {
            return "\n### Agenda del Usuario:\nNo tienes eventos agendados para {$period}.\n\n".$tasksBlock;
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

        return $context.$tasksBlock;
    }
}
