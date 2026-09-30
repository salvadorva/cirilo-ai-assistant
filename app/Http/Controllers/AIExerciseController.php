<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Support\AiLog as Log;
use Illuminate\Support\Facades\Storage;

class AIExerciseController extends Controller
{
    /**
     * Genera ejercicios personalizados para el usuario según su nivel y área más débil
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generatePersonalizedExercises(Request $request)
    {
        try {
            // Obtener el nivel del usuario de la sesión o del request
            $level = $request->input('level', session('level', 'A1'));

            // Obtener el tipo de ejercicio del request o usar el más débil si no se especifica
            $type = $request->input('type');

            // Si no se especifica un tipo, obtener el área más débil del usuario
            if (! $type) {
                // Obtener el usuario actual
                $user = auth()->user();

                // Obtener el nivel de inglés del usuario
                $userLevel = \App\Models\UserEnglishLevel::where('user_id', $user->id)->first();

                if ($userLevel) {
                    $type = $this->determineWeakestArea($userLevel);
                } else {
                    // Si no hay datos de nivel, usar vocabulario por defecto
                    $type = 'vocabulary';
                }
            }

            // Generar el ejercicio
            $result = $this->generateExercise($level, $type);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'exercise' => $result['exercise'],
                    'type' => $type,
                    'level' => $level,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'type' => $type,
                    'level' => $level,
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error al generar ejercicios personalizados: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Error al generar ejercicios personalizados: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determina el tipo de ejercicio más débil del usuario basado en sus puntuaciones
     *
     * @param  object  $userLevel  Objeto con las puntuaciones del usuario
     * @return string Tipo de ejercicio más débil (vocabulary, grammar, speaking, listening)
     */
    public function determineWeakestArea($userLevel)
    {
        // Mapear las puntuaciones por tipo
        $scores = [
            'vocabulary' => $userLevel->vocabulary_score,
            'grammar' => $userLevel->grammar_score,
            'speaking' => $userLevel->speaking_score,
            'listening' => $userLevel->listening_score,
        ];

        // Encontrar la puntuación más baja
        $minScore = min($scores);
        $weakestAreas = array_keys($scores, $minScore);

        // Si hay varias áreas con la misma puntuación mínima, elegir una aleatoriamente
        return $weakestAreas[array_rand($weakestAreas)];
    }

    /**
     * Genera ejercicios dinámicos utilizando OpenAI según el nivel y tipo
     *
     * @param  string  $level  Nivel del usuario (A1, A2, B1, B2, C1, C2)
     * @param  string  $type  Tipo de ejercicio (vocabulary, grammar, speaking, listening)
     * @return array Ejercicio generado con título, instrucciones, contenido y ejemplo
     */
    public function generateExercise($level, $type)
    {
        try {
            // Obtener credenciales de API
            $credentials = $this->getApiCredentials();

            // Generar el prompt para OpenAI
            $prompt = $this->generatePrompt($level, $type);

            Log::info('Generando ejercicio de '.$type.' para nivel '.$level);

            // Realizar solicitud a la API de OpenAI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->post($credentials['base_url'].'/chat/completions', [
                'model' => config('ai.models.vision'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un profesor de inglés especializado en crear ejercicios personalizados.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $content = $responseData['choices'][0]['message']['content'];

                // Intentar extraer el JSON de la respuesta
                $jsonStart = strpos($content, '{');
                $jsonEnd = strrpos($content, '}');

                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonContent = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
                    $exercise = json_decode($jsonContent, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Si es un ejercicio de listening o speaking, generar audio
                        if (in_array($type, ['listening', 'speaking']) && isset($exercise['audio_text'])) {
                            $audioUrl = $this->generateAudio($exercise['audio_text']);
                            $exercise['audio_url'] = $audioUrl;
                        }

                        return [
                            'success' => true,
                            'exercise' => $exercise,
                        ];
                    } else {
                        Log::error('Error al decodificar JSON: '.json_last_error_msg());
                        Log::error('Contenido recibido: '.$content);

                        return [
                            'success' => false,
                            'error' => 'Error al procesar la respuesta de OpenAI: formato JSON inválido',
                            'raw_content' => $content,
                        ];
                    }
                } else {
                    Log::error('No se encontró formato JSON en la respuesta');
                    Log::error('Contenido recibido: '.$content);

                    return [
                        'success' => false,
                        'error' => 'La respuesta de OpenAI no contiene un formato JSON válido',
                        'raw_content' => $content,
                    ];
                }
            } else {
                Log::error('Error en la respuesta de OpenAI: '.$response->status());

                return [
                    'success' => false,
                    'error' => 'Error al comunicarse con la API de OpenAI: '.$response->status(),
                    'details' => $response->json(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Excepción al generar ejercicio: '.$e->getMessage());

            return [
                'success' => false,
                'error' => 'Error al generar el ejercicio: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Genera audio a partir de texto utilizando la API de OpenAI
     *
     * @param  string  $text  Texto para convertir a audio
     * @return string|null URL del archivo de audio generado, o null si falla
     */
    public function generateAudio($text)
    {
        try {
            // Validar que el texto no esté vacío
            if (empty($text) || trim($text) === '') {
                Log::warning('Intento de generar audio con texto vacío');

                return null;
            }

            // Limpiar y validar el texto
            $text = trim($text);
            if (strlen($text) < 3) {
                Log::warning('Texto demasiado corto para generar audio: '.$text);

                return null;
            }

            // Obtener credenciales de API
            $credentials = $this->getApiCredentials();

            // Limitar el texto si es demasiado largo (OpenAI tiene límite de 4096 tokens)
            if (strlen($text) > 4000) {
                $text = substr($text, 0, 4000);
            }

            Log::info('Generando audio para texto de '.strlen($text).' caracteres');

            // Realizar solicitud a la API de OpenAI para text-to-speech
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->post($credentials['base_url'].'/audio/speech', [
                'model' => config('ai.models.tts'),
                'input' => $text,
                'voice' => 'echo',
                'output_format' => 'mp3',
            ]);

            if ($response->successful()) {
                // Guardar audios de ejercicios en carpeta dinámica
                $fileName = 'audio/dynamic/exercise_'.uniqid().'.mp3';

                // Asegurar que existe el directorio
                if (! Storage::disk('public')->exists('audio/dynamic')) {
                    Storage::disk('public')->makeDirectory('audio/dynamic');
                }

                Storage::disk('public')->put($fileName, $response->body());

                // Construir la URL del audio
                $audioUrl = Storage::disk('public')->url($fileName);

                Log::info('Audio generado exitosamente: '.$audioUrl);

                return $audioUrl;
            } else {
                Log::error('Error al generar audio: '.$response->status().' - '.$response->body());

                return null;
            }
        } catch (\Exception $e) {
            Log::error('Excepción al generar audio: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Obtiene las credenciales de la API de OpenAI
     *
     * @return array Credenciales de la API
     */
    public function getApiCredentials()
    {
        return [
            'api_key' => config('services.openai.api_key', ''),
            'base_url' => 'https://api.openai.com/v1',
        ];
    }

    /**
     * Analiza las puntuaciones del usuario y redirecciona a la práctica en su área más débil
     *
     * @param  string  $level  Nivel del usuario
     * @return \Illuminate\Http\RedirectResponse
     */
    public function generateExercisesView($level)
    {
        // Validar que el nivel sea válido
        $validLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        if (! in_array($level, $validLevels)) {
            $level = 'A1'; // Nivel por defecto
        }

        // Obtener las puntuaciones del usuario para análisis
        $scores = [];
        $user = auth()->user();

        if ($user) {
            $userLevel = \App\Models\UserEnglishLevel::where('user_id', $user->id)
                ->where('level', $level)
                ->first();

            if ($userLevel && $userLevel->section_scores) {
                $scores = $userLevel->section_scores;
            }
        }

        // Obtener de la sesión las puntuaciones más recientes
        $sessionScores = session('section_scores', []);
        $scores = array_merge($scores, $sessionScores);

        // Asegurar que tenemos las cuatro categorías principales
        $categories = ['vocabulary', 'grammar', 'speaking', 'listening'];
        foreach ($categories as $category) {
            if (! isset($scores[$category])) {
                $scores[$category] = 0;
            }
        }

        // Determinar el área más débil del usuario
        $minScore = PHP_INT_MAX;
        $weakestArea = 'vocabulary'; // Valor predeterminado

        foreach ($categories as $category) {
            // Ignorar las entradas que terminan en _last
            if (strpos($category, '_last') === false && isset($scores[$category])) {
                if ($scores[$category] < $minScore) {
                    $minScore = $scores[$category];
                    $weakestArea = $category;
                }
            }
        }

        // Preparar mensaje para SweetAlert
        $areaNames = [
            'vocabulary' => 'Vocabulario',
            'grammar' => 'Gramática',
            'speaking' => 'Conversación',
            'listening' => 'Comprensión Auditiva',
        ];

        $strengthArea = array_search(max($scores), $scores);

        // Guardar el análisis en la sesión para mostrarlo en la vista de práctica
        session([
            'ai_analysis' => [
                'weakest_area' => $weakestArea,
                'weakest_area_name' => $areaNames[$weakestArea] ?? ucfirst($weakestArea),
                'strength_area' => $strengthArea,
                'strength_area_name' => $areaNames[$strengthArea] ?? ucfirst($strengthArea),
                'scores' => $scores,
            ],
        ]);

        // Redirigir directamente a la práctica en el área más débil
        return redirect()->route('tutor.practice', ['type' => $weakestArea, 'level' => $level])
            ->with('ai_recommendation', true)
            ->with('message', "Basado en tu evaluación, hemos identificado que tu área más débil es {$areaNames[$weakestArea]}. Te hemos generado un ejercicio personalizado para ayudarte a mejorar en esta área.");
    }

    /**
     * Endpoint para generar audio desde texto
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateAudioEndpoint(Request $request)
    {
        try {
            $text = $request->input('text');

            if (empty($text)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El texto no puede estar vacío',
                ], 400);
            }

            $audioUrl = $this->generateAudio($text);

            if (! $audioUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo generar el audio',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'audio_url' => $audioUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en generateAudioEndpoint: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al generar audio: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera un prompt para OpenAI según el nivel y tipo de ejercicio
     *
     * @param  string  $level  Nivel del usuario (A1, A2, B1, B2, C1, C2)
     * @param  string  $type  Tipo de ejercicio (vocabulary, grammar, speaking, listening)
     * @return string Prompt para OpenAI
     */
    private function generatePrompt($level, $type)
    {
        // Descripción del nivel según el Marco Común Europeo
        $levelDescriptions = [
            'A1' => 'Nivel principiante. El usuario puede entender y utilizar expresiones cotidianas básicas y frases sencillas.',
            'A2' => 'Nivel elemental. El usuario puede comunicarse en tareas simples y rutinarias que requieren un intercambio simple y directo de información.',
            'B1' => 'Nivel intermedio. El usuario puede comprender los puntos principales de textos claros y en lengua estándar y producir textos sencillos sobre temas familiares.',
            'B2' => 'Nivel intermedio alto. El usuario puede entender las ideas principales de textos complejos y puede interactuar con cierto grado de fluidez y espontaneidad.',
            'C1' => 'Nivel avanzado. El usuario puede comprender una amplia variedad de textos extensos y con cierto nivel de exigencia y expresarse de forma fluida y espontánea.',
            'C2' => 'Nivel dominio. El usuario puede comprender con facilidad prácticamente todo lo que oye o lee y expresarse espontáneamente con gran fluidez y precisión.',
        ];

        // Temas variados según el nivel para evitar repetición
        $topicsByLevel = [
            'A1' => ['family and friends', 'hobbies and free time', 'food and drinks', 'shopping', 'weather', 'personal information', 'home and rooms', 'colors and numbers'],
            'A2' => ['travel and holidays', 'work and jobs', 'health and body', 'education and school', 'transportation', 'daily routine', 'sports and exercise', 'technology basics'],
            'B1' => ['environment and nature', 'culture and traditions', 'media and news', 'relationships', 'future plans', 'past experiences', 'opinions and preferences', 'social issues'],
            'B2' => ['career development', 'global issues', 'science and innovation', 'arts and literature', 'economics and business', 'psychology and behavior', 'ethics and values', 'lifestyle choices'],
            'C1' => ['political systems', 'philosophical concepts', 'academic research', 'professional expertise', 'cultural analysis', 'historical perspectives', 'complex social dynamics', 'abstract thinking'],
            'C2' => ['theoretical frameworks', 'critical analysis', 'specialized knowledge', 'nuanced argumentation', 'interdisciplinary topics', 'advanced academic discourse', 'professional debates', 'sophisticated reasoning'],
        ];

        // Obtener temas usados recientemente de la sesión
        $usedTopics = session("used_topics_{$type}", []);

        // Seleccionar un tema aleatorio del nivel correspondiente que no haya sido usado recientemente
        $availableTopics = $topicsByLevel[$level] ?? $topicsByLevel['A1'];

        // Filtrar los temas ya usados
        $unusedTopics = array_diff($availableTopics, $usedTopics);

        // Si no quedan temas sin usar, reiniciar la lista
        if (empty($unusedTopics)) {
            $unusedTopics = $availableTopics;
            // Limpiar el historial de temas usados
            session(["used_topics_{$type}" => []]);
            $usedTopics = [];
        }

        // Seleccionar un tema aleatorio de los no usados
        $randomTopic = $unusedTopics[array_rand($unusedTopics)];

        // Registrar el tema usado en la sesión (máximo 5 temas por tipo)
        $usedTopics[] = $randomTopic;
        if (count($usedTopics) > 5) {
            array_shift($usedTopics); // Eliminar el tema más antiguo
        }
        session(["used_topics_{$type}" => $usedTopics]);

        // Registrar en el log el tema seleccionado
        Log::info("Tema seleccionado para ejercicio de {$type} nivel {$level}: {$randomTopic}");

        // Instrucciones específicas según el tipo de ejercicio
        $typeInstructions = [
            'vocabulary' => "Crea un ejercicio de vocabulario sobre el tema '{$randomTopic}' que incluya palabras y frases relevantes para este nivel. El ejercicio debe incluir definiciones, ejemplos de uso y actividades para practicar.",
            'grammar' => "Crea un ejercicio de gramática enfocado en las estructuras gramaticales apropiadas para este nivel, usando contexto del tema '{$randomTopic}'. Incluye explicaciones claras, ejemplos y ejercicios prácticos.",
            'speaking' => "Crea un ejercicio de expresión oral sobre el tema '{$randomTopic}' que incluya preguntas de conversación, situaciones para practicar y vocabulario útil. Proporciona estructuras para ayudar al usuario. Incluye un texto para audio que contenga las instrucciones habladas del ejercicio. IMPORTANTE: Varía el tema, no uses siempre 'daily routine'.",
            'listening' => "Crea un ejercicio de comprensión auditiva sobre el tema '{$randomTopic}' con un texto que será leído al usuario. Incluye preguntas de comprensión y vocabulario clave.",
        ];

        // Formato de respuesta esperado
        $responseFormat = <<<EOT
Por favor, proporciona tu respuesta en el siguiente formato JSON exacto (todos los campos deben ser strings, no arrays):
{
  "title": "Título del ejercicio (string)",
  "instructions": "Instrucciones claras y detalladas para el usuario (string)",
  "content": "Contenido principal del ejercicio como un solo texto (string)",
  "example": "Un ejemplo de cómo resolver el ejercicio (string)",
  "audio_text": "Texto que debe ser convertido a audio - para ejercicios de speaking y listening (string)"
}

IMPORTANTE: 
- Todos los valores deben ser strings simples, NO arrays. 
- Si necesitas incluir múltiples elementos, únelos en un solo string usando saltos de línea (\n) o separadores apropiados.
- Para ejercicios de SPEAKING: el campo audio_text debe contener las instrucciones del ejercicio en inglés, como si fueras un tutor hablando al estudiante.
- Para ejercicios de LISTENING: el campo audio_text debe contener el texto que el estudiante debe escuchar y comprender.
- Para ejercicios de VOCABULARY y GRAMMAR: el campo audio_text puede estar vacío o contener una breve introducción al ejercicio.
- VARÍA LOS TEMAS: No repitas siempre el mismo tema. El tema seleccionado para este ejercicio es: {$randomTopic}
EOT;

        // Construir el prompt completo
        $prompt = 'Eres un profesor de inglés especializado en crear ejercicios personalizados para estudiantes. ';
        $prompt .= "Necesito que crees un ejercicio de {$type} para un estudiante de nivel {$level}. ";
        $prompt .= "Descripción del nivel: {$levelDescriptions[$level]}. ";
        $prompt .= "TEMA ESPECÍFICO: {$randomTopic} - Asegúrate de usar este tema y no repetir siempre 'daily routine'. ";
        $prompt .= "\n\n{$typeInstructions[$type]}";
        $prompt .= "\n\n{$responseFormat}";

        return $prompt;
    }
}
