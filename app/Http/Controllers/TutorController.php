<?php

namespace App\Http\Controllers;

use App\Models\UserEnglishLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Support\AiLog as Log;

class TutorController extends Controller
{
    /**
     * Muestra la página principal del tutor
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = auth()->user();

        // Obtener cursos personalizados del usuario
        $userCourses = \App\Models\Course::where('user_id', $user->id)
            ->with([
                'sessions',
                'progress' => function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                },
            ])
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        // Obtener datos del curso de inglés (mantener compatibilidad)
        $userLevel = \App\Models\UserEnglishLevel::where('user_id', $user->id)->first();

        $englishProgress = [
            'level' => $userLevel ? $userLevel->level : 'beginner',
            'listening_score' => session('listening_score', $userLevel->listening_score ?? 0),
            'vocabulary_score' => session('vocabulary_score', $userLevel->vocabulary_score ?? 0),
            'grammar_score' => session('grammar_score', $userLevel->grammar_score ?? 0),
            'speaking_score' => session('speaking_score', $userLevel->speaking_score ?? 0),
        ];

        // --- Lógica del Tutor IA (Cirilo) ---
        $cacheKey = 'tutor_welcome_'.$user->id;
        $tutorMessage = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (! $tutorMessage) {
            // Calcular días de inactividad en el tutor
            $lastActivity = $userLevel ? $userLevel->evaluated_at : null;
            $daysInactive = $lastActivity ? now()->diffInDays($lastActivity) : 0;

            // Ejercicios del tutor
            $lastExercise = $user->exerciseResults()->orderBy('created_at', 'desc')->first();
            $totalExercises = $user->exerciseResults()->count();

            // Determinar área más débil según puntuaciones
            $scores = [
                'vocabulario' => $englishProgress['vocabulary_score'],
                'gramática' => $englishProgress['grammar_score'],
                'speaking' => $englishProgress['speaking_score'],
                'listening' => $englishProgress['listening_score'],
            ];
            $weakestSkill = array_search(min($scores), $scores);
            $strongestSkill = array_search(max($scores), $scores);

            // Cursos completados
            $completedCourses = \App\Models\CourseProgress::where('user_id', $user->id)
                ->where('completed', true)
                ->count();

            // Construir contexto específico del tutor (SIN datos de login/juegos/conversaciones)
            $context = [
                'name' => $user->name,
                'scope' => 'tutor', // Indica que es contexto del tutor

                // Tutor/Inglés
                'english_level' => $englishProgress['level'],
                'listening_score' => $englishProgress['listening_score'],
                'vocabulary_score' => $englishProgress['vocabulary_score'],
                'grammar_score' => $englishProgress['grammar_score'],
                'speaking_score' => $englishProgress['speaking_score'],
                'weakest_skill' => $weakestSkill,
                'strongest_skill' => $strongestSkill,

                // Ejercicios
                'total_exercises' => $totalExercises,
                'last_exercise_type' => $lastExercise ? $lastExercise->type : null,
                'last_exercise_score' => $lastExercise ? $lastExercise->score : null,
                'last_exercise_date' => $lastExercise ? $lastExercise->created_at->diffForHumans() : null,

                // Cursos
                'custom_courses_count' => $userCourses->count(),
                'courses_completed' => $completedCourses,

                // Días sin actividad en el tutor
                'days_inactive' => $daysInactive,
            ];

            // Llamar al AIController
            $aiController = new \App\Http\Controllers\AIController;
            $request = new \Illuminate\Http\Request(['context' => $context]);
            $response = $aiController->generateTutorWelcome($request);
            $data = json_decode($response->getContent(), true);

            if ($data['success']) {
                $tutorMessage = [
                    'message' => $data['message'],
                    'audioUrl' => $data['audioUrl'],
                    'generated_at' => now()->toIso8601String(),
                ];
                // Guardar en caché por 1 hora
                \Illuminate\Support\Facades\Cache::put($cacheKey, $tutorMessage, 3600);
            }
        }
        // --- Fin Lógica Tutor IA ---

        return view('tutor.index', compact('userCourses', 'englishProgress', 'tutorMessage'));
    }

    /**
     * Forzar actualización del mensaje del tutor IA
     */
    public function refreshMessage()
    {
        $user = auth()->user();
        $cacheKey = 'tutor_welcome_'.$user->id;

        // Borrar caché
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        // Obtener datos actuales del usuario
        $userCourses = \App\Models\Course::where('user_id', $user->id)->get();
        $userLevel = \App\Models\UserEnglishLevel::where('user_id', $user->id)->first();

        $englishProgress = [
            'level' => $userLevel ? $userLevel->level : 'beginner',
            'listening_score' => $userLevel->listening_score ?? 0,
            'vocabulary_score' => $userLevel->vocabulary_score ?? 0,
            'grammar_score' => $userLevel->grammar_score ?? 0,
            'speaking_score' => $userLevel->speaking_score ?? 0,
        ];

        // Calcular días de inactividad en el tutor
        $lastActivity = $userLevel ? $userLevel->evaluated_at : null;
        $daysInactive = $lastActivity ? now()->diffInDays($lastActivity) : 0;

        // Ejercicios del tutor
        $lastExercise = $user->exerciseResults()->orderBy('created_at', 'desc')->first();
        $totalExercises = $user->exerciseResults()->count();

        // Determinar área más débil según puntuaciones
        $scores = [
            'vocabulario' => $englishProgress['vocabulary_score'],
            'gramática' => $englishProgress['grammar_score'],
            'speaking' => $englishProgress['speaking_score'],
            'listening' => $englishProgress['listening_score'],
        ];
        $weakestSkill = array_search(min($scores), $scores);
        $strongestSkill = array_search(max($scores), $scores);

        // Cursos completados
        $completedCourses = \App\Models\CourseProgress::where('user_id', $user->id)
            ->where('completed', true)
            ->count();

        // Construir contexto simple del tutor (SIN datos de login/juegos/conversaciones)
        $context = [
            'name' => $user->name,
            'scope' => 'tutor',

            // Tutor/Inglés
            'english_level' => $englishProgress['level'],
            'listening_score' => $englishProgress['listening_score'],
            'vocabulary_score' => $englishProgress['vocabulary_score'],
            'grammar_score' => $englishProgress['grammar_score'],
            'speaking_score' => $englishProgress['speaking_score'],
            'weakest_skill' => $weakestSkill,
            'strongest_skill' => $strongestSkill,

            // Ejercicios
            'total_exercises' => $totalExercises,
            'last_exercise_type' => $lastExercise ? $lastExercise->type : null,
            'last_exercise_score' => $lastExercise ? $lastExercise->score : null,
            'last_exercise_date' => $lastExercise ? $lastExercise->created_at->diffForHumans() : null,

            // Cursos
            'custom_courses_count' => $userCourses->count(),
            'courses_completed' => $completedCourses,

            // Días sin actividad
            'days_inactive' => $daysInactive,
        ];

        // Generar nuevo mensaje
        $aiController = new \App\Http\Controllers\AIController;
        $request = new \Illuminate\Http\Request(['context' => $context]);
        $response = $aiController->generateTutorWelcome($request);
        $data = json_decode($response->getContent(), true);

        if ($data['success']) {
            $tutorMessage = [
                'message' => $data['message'],
                'audioUrl' => $data['audioUrl'],
                'generated_at' => now()->toIso8601String(),
            ];
            \Illuminate\Support\Facades\Cache::put($cacheKey, $tutorMessage, 3600);

            return response()->json([
                'success' => true,
                'summary' => $tutorMessage,
            ]);
        }

        return response()->json(['success' => false, 'error' => 'No se pudo generar el mensaje'], 500);
    }

    /**
     * Redirecciona a la vista de generación de ejercicios con IA
     *
     * @param  string  $level  Nivel del usuario
     * @return \Illuminate\Http\RedirectResponse
     */
    public function generateAIExercises($level)
    {
        // Validar que el nivel sea válido
        $validLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        if (! in_array($level, $validLevels)) {
            $level = 'A1'; // Nivel por defecto
        }

        // Redirigir a la ruta que usa AIExerciseController::generateExercisesView
        return redirect()->route('tutor.generate.ai', ['level' => $level]);
    }

    /**
     * Muestra la vista de evaluación inicial de nivel
     *
     * @return \Illuminate\View\View
     */
    public function evaluation()
    {
        // Generar preguntas para la evaluación
        $quiz = $this->generateEvaluationQuiz();

        // Guardar las preguntas en la sesión para validarlas después
        session(['evaluation_quiz' => $quiz]);

        return view('tutor.evaluation', ['quiz' => $quiz]);
    }

    /**
     * Procesa la evaluación inicial y determina el nivel del usuario
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeEvaluation(Request $request)
    {
        try {
            // Recuperar las preguntas de la sesión
            $quiz = session('evaluation_quiz');

            if (! $quiz) {
                return redirect()->route('tutor.evaluation')
                    ->with('error', 'No se encontraron las preguntas de la evaluación. Por favor, intenta de nuevo.');
            }

            // Debug: Verificar la estructura del quiz
            Log::info('Estructura del quiz:', ['quiz_keys' => array_keys($quiz)]);
            foreach ($quiz as $section => $questions) {
                if (is_array($questions) && ! empty($questions)) {
                    Log::info("Primera pregunta de $section:", [array_keys($questions[0])]);
                }
            }

            // Procesar respuestas y calcular puntuación
            $scores = $this->calculateEvaluationScores($request, $quiz);

            // Determinar nivel según la puntuación total
            $totalScore = $scores['total'];
            $level = $this->determineLevel($totalScore);

            // Guardar nivel y puntuaciones en la sesión
            session(['level' => $level]);
            session([
                'section_scores' => [
                    'listening' => $scores['listening'],
                    'vocabulary' => $scores['vocabulary'],
                    'grammar' => $scores['grammar'],
                    'speaking' => $scores['speaking'],
                ],
            ]);

            // Si el usuario está autenticado, guardar en la base de datos
            if (Auth::check()) {
                $user = Auth::user();

                // Recolectar todas las respuestas del usuario
                $userAnswers = [];
                $prefixes = ['l' => 'listening', 'v' => 'vocabulary', 'g' => 'grammar', 's' => 'speaking'];

                // Recorrer todas las respuestas del request
                foreach ($request->all() as $key => $value) {
                    if (preg_match('/^([lvgs])_(\d+)$/', $key, $matches)) {
                        $prefix = $matches[1];
                        $questionId = $matches[2];
                        $userAnswers["{$prefix}_{$questionId}"] = $value;
                    }
                }

                // Guardar en la base de datos
                $sectionScores = [
                    'listening' => $scores['listening'],
                    'vocabulary' => $scores['vocabulary'],
                    'grammar' => $scores['grammar'],
                    'speaking' => $scores['speaking'],
                ];

                $userProgress = UserEnglishLevel::updateOrCreate(
                    [
                        'user_id' => auth()->id(),
                        'level' => $level,
                    ],
                    [
                        'score' => $scores['total'],
                        'section_scores' => $sectionScores,
                        'answers' => $userAnswers,
                        'evaluated_at' => now(),
                    ]
                );

                Log::info('Resultados guardados en BD', [
                    'user_id' => auth()->id(),
                    'level' => $level,
                    'score' => $scores['total'],
                    'section_scores' => $sectionScores,
                    'answers_count' => count($userAnswers),
                ]);
            }

            // Redirigir a la página principal del tutor con mensaje de éxito
            return redirect()->route('tutor')
                ->with('success', "¡Evaluación completada! Tu nivel de inglés es {$level}.");

        } catch (\Exception $e) {
            Log::error('Error en storeEvaluation: '.$e->getMessage());

            return redirect()->route('tutor.evaluation')
                ->with('error', 'Ocurrió un error al procesar la evaluación. Por favor, intenta de nuevo.');
        }
    }

    /**
     * Genera las preguntas para la evaluación inicial desde el archivo quiz.json
     * Selecciona 5 preguntas al azar de cada sección
     *
     * @return array
     */
    private function generateEvaluationQuiz()
    {
        // Ruta al archivo quiz.json
        $quizFilePath = base_path('quiz.json');

        // Verificar si el archivo existe
        if (! file_exists($quizFilePath)) {
            Log::error('El archivo quiz.json no se encuentra en: '.$quizFilePath);

            return $this->getDefaultQuestions();
        }

        // Leer el contenido del archivo JSON
        $jsonContent = file_get_contents($quizFilePath);
        if ($jsonContent === false) {
            Log::error('No se pudo leer el archivo quiz.json');

            return $this->getDefaultQuestions();
        }

        // Decodificar el JSON
        $quizData = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Error al decodificar el archivo quiz.json: '.json_last_error_msg());

            return $this->getDefaultQuestions();
        }

        // Inicializar el arreglo de preguntas
        $questions = [
            'listening' => [],
            'vocabulary' => [],
            'grammar' => [],
            'speaking' => [],
        ];

        // Función para seleccionar preguntas al azar
        $selectRandomQuestions = function ($allQuestions, $count = 5) {
            if (count($allQuestions) <= $count) {
                return $allQuestions; // Devolver todas si hay menos de $count
            }

            // Mezclar el array y seleccionar los primeros $count elementos
            shuffle($allQuestions);

            return array_slice($allQuestions, 0, $count);
        };

        // Procesar cada sección
        foreach ($questions as $section => &$sectionQuestions) {
            if (! isset($quizData[$section]) || ! is_array($quizData[$section])) {
                Log::warning("La sección '$section' no existe o no es un array en quiz.json");

                continue;
            }

            // Seleccionar 5 preguntas al azar
            $sectionQuestions = $selectRandomQuestions($quizData[$section], 5);

            // Renumerar los IDs para que sean secuenciales empezando en 1
            foreach ($sectionQuestions as $index => &$question) {
                $question['id'] = $index + 1;
            }
        }

        return $questions;
    }

    /**
     * Devuelve preguntas por defecto en caso de error al cargar el archivo
     *
     * @return array
     */
    private function getDefaultQuestions()
    {
        Log::info('Usando preguntas por defecto');

        return [
            'listening' => [
                ['id' => 1, 'audio' => 'What is your name?', 'answer' => 'What is your name?'],
                ['id' => 2, 'audio' => 'I like to play tennis on weekends.', 'answer' => 'I like to play tennis on weekends.'],
                ['id' => 3, 'audio' => 'She doesn\'t have time to go to the movies.', 'answer' => 'She doesn\'t have time to go to the movies.'],
            ],
            'vocabulary' => [
                ['id' => 1, 'question' => 'What is the opposite of hot?', 'options' => ['Cold', 'Warm', 'Big', 'Small'], 'answer' => 'Cold'],
                ['id' => 2, 'question' => 'What do you wear on your feet?', 'options' => ['Hat', 'Shoes', 'Gloves', 'Belt'], 'answer' => 'Shoes'],
            ],
            'grammar' => [
                ['id' => 1, 'question' => 'She _____ to the store yesterday.', 'options' => ['go', 'goes', 'went', 'going'], 'answer' => 'went'],
                ['id' => 2, 'question' => 'They _____ watching TV right now.', 'options' => ['are', 'is', 'am', 'be'], 'answer' => 'are'],
            ],
            'speaking' => [
                ['id' => 1, 'prompt' => 'What is your name?', 'criteria' => 'Name or phrase like "My name is..."', 'expected' => 'A clear self-introduction.'],
            ],
        ];
    }

    /**
     * Calcula las puntuaciones de la evaluación
     *
     * @param  array  $quiz
     * @return array
     */
    private function calculateEvaluationScores(Request $request, $quiz)
    {
        $scores = [
            'listening' => 0,
            'vocabulary' => 0,
            'grammar' => 0,
            'speaking' => 0,
            'total' => 0,
        ];

        // Evaluar listening (máximo 5 puntos)
        if (! empty($quiz['listening'])) {
            Log::info('Procesando sección listening');
            $totalListeningQuestions = count($quiz['listening']);
            $pointsPerListeningQuestion = $totalListeningQuestions > 0 ? 5 / $totalListeningQuestions : 0;

            foreach ($quiz['listening'] as $index => $question) {
                $questionId = $question['id'];
                $userAnswer = $request->input('l_'.$questionId);

                Log::info("Pregunta listening #$index", [
                    'question_id' => $questionId,
                    'user_answer' => $userAnswer,
                    'correct_answer' => $question['answer'] ?? 'N/A',
                    'is_correct' => isset($question['answer']) && strtolower(trim($userAnswer)) === strtolower(trim($question['answer'])),
                ]);

                if (isset($question['answer'])) {
                    $correctAnswer = $question['answer'];
                    // Comparación más flexible para listening
                    $userAnswerCleaned = strtolower(trim($userAnswer));
                    $correctAnswerCleaned = strtolower(trim($correctAnswer));
                    $userAnswerNormalized = preg_replace('/[^a-z0-9\s]/', '', $userAnswerCleaned);
                    $correctAnswerNormalized = preg_replace('/[^a-z0-9\s]/', '', $correctAnswerCleaned);

                    Log::info('Comparando respuestas', [
                        'usuario' => $userAnswerNormalized,
                        'correcta' => $correctAnswerNormalized,
                        'coinciden' => $userAnswerNormalized === $correctAnswerNormalized,
                    ]);

                    if ($userAnswerNormalized === $correctAnswerNormalized) {
                        $scores['listening'] += $pointsPerListeningQuestion;
                        Log::info("¡Respuesta correcta! Puntos: $pointsPerListeningQuestion");
                    }
                } else {
                    Log::warning('Pregunta sin campo answer', ['section' => 'listening', 'question' => $question]);
                }
            }
            Log::info("Puntuación total listening: {$scores['listening']}");
        }

        // Evaluar vocabulary (máximo 5 puntos)
        if (! empty($quiz['vocabulary'])) {
            Log::info('Procesando sección vocabulary');
            $totalVocabularyQuestions = count($quiz['vocabulary']);
            $pointsPerVocabularyQuestion = $totalVocabularyQuestions > 0 ? 5 / $totalVocabularyQuestions : 0;

            foreach ($quiz['vocabulary'] as $index => $question) {
                Log::info("Pregunta vocabulary #$index", ['question' => $question['id'], 'has_answer' => isset($question['answer'])]);
                $userAnswer = $request->input('v_'.$question['id']);

                if (isset($question['answer'])) {
                    $correctAnswer = $question['answer'];
                    if ($userAnswer == $correctAnswer) {
                        $scores['vocabulary'] += $pointsPerVocabularyQuestion;
                    }
                } else {
                    Log::warning('Pregunta sin campo answer', ['section' => 'vocabulary', 'question' => $question]);
                }
            }
        }

        // Evaluar grammar (máximo 5 puntos)
        if (! empty($quiz['grammar'])) {
            Log::info('Procesando sección grammar');
            $totalGrammarQuestions = count($quiz['grammar']);
            $pointsPerGrammarQuestion = $totalGrammarQuestions > 0 ? 5 / $totalGrammarQuestions : 0;

            foreach ($quiz['grammar'] as $index => $question) {
                Log::info("Pregunta grammar #$index", ['question' => $question['id'], 'has_answer' => isset($question['answer'])]);
                $userAnswer = $request->input('g_'.$question['id']);

                if (isset($question['answer'])) {
                    $correctAnswer = $question['answer'];
                    if ($userAnswer == $correctAnswer) {
                        $scores['grammar'] += $pointsPerGrammarQuestion;
                    }
                } else {
                    Log::warning('Pregunta sin campo answer', ['section' => 'grammar', 'question' => $question]);
                }
            }
        }

        // Evaluar speaking (máximo 5 puntos)
        if (! empty($quiz['speaking'])) {
            $totalSpeakingQuestions = count($quiz['speaking']);
            $pointsPerSpeakingQuestion = $totalSpeakingQuestions > 0 ? 5 / $totalSpeakingQuestions : 0;

            // Para speaking, no necesitamos la respuesta correcta, solo verificar si el usuario respondió
            foreach ($quiz['speaking'] as $index => $question) {
                $questionId = $question['id'];
                $userAnswer = $request->input('s_'.$questionId);

                Log::info("Pregunta speaking #$index", [
                    'question_id' => $questionId,
                    'answer_length' => strlen($userAnswer ?? ''),
                ]);

                if (! empty($userAnswer)) {
                    $length = strlen($userAnswer);
                    $points = 0;

                    if ($length > 100) {
                        $points = $pointsPerSpeakingQuestion; // Respuesta completa
                    } elseif ($length > 50) {
                        $points = $pointsPerSpeakingQuestion * 0.6; // Respuesta media (60%)
                    } else {
                        $points = $pointsPerSpeakingQuestion * 0.3; // Respuesta corta (30%)
                    }

                    $scores['speaking'] += $points;
                    Log::info("Puntos speaking: $points (longitud: $length)");
                } else {
                    Log::info("Pregunta speaking #$index sin respuesta");
                }
            }
            Log::info("Puntuación total speaking: {$scores['speaking']}");
        }

        // Limitar puntuaciones a 5 (por si acaso)
        $scores['listening'] = min(5, $scores['listening']);
        $scores['vocabulary'] = min(5, $scores['vocabulary']);
        $scores['grammar'] = min(5, $scores['grammar']);
        $scores['speaking'] = min(5, $scores['speaking']);

        // Calcular puntuación total (sobre 100)
        $scores['total'] = ($scores['listening'] + $scores['vocabulary'] + $scores['grammar'] + $scores['speaking']) * 5;

        // Guardar respuestas del usuario en la sesión
        $userAnswers = [];
        foreach (['listening', 'vocabulary', 'grammar', 'speaking'] as $section) {
            if (! empty($quiz[$section])) {
                foreach ($quiz[$section] as $question) {
                    $questionId = $question['id'];
                    $prefix = substr($section, 0, 1); // l, v, g, s
                    $userAnswers["{$prefix}_{$questionId}"] = $request->input("{$prefix}_{$questionId}");
                }
            }
        }
        session(['evaluation_answers' => $userAnswers]);

        Log::info('Puntuaciones calculadas:', $scores);

        return $scores;
    }

    /**
     * Determina el nivel de inglés según la puntuación
     *
     * @param  float  $score
     * @return string
     */
    private function determineLevel($score)
    {
        if ($score >= 90) {
            return 'C2';
        } elseif ($score >= 75) {
            return 'C1';
        } elseif ($score >= 60) {
            return 'B2';
        } elseif ($score >= 45) {
            return 'B1';
        } elseif ($score >= 30) {
            return 'A2';
        } else {
            return 'A1';
        }
    }

    /**
     * Evalúa ejercicios de speaking usando OpenAI
     */
    public function evaluateSpeaking(Request $request)
    {
        try {
            $userAnswer = $request->input('answer');
            $exerciseContent = $request->input('exercise_content');
            $level = $request->input('level');

            if (empty($userAnswer) || empty($exerciseContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan datos para la evaluación',
                ], 400);
            }

            Log::info('Evaluando speaking', [
                'user_answer' => $userAnswer,
                'exercise_content' => $exerciseContent,
                'level' => $level,
            ]);

            // Crear prompt específico para speaking
            $prompt = $this->createSpeakingEvaluationPrompt($userAnswer, $exerciseContent, $level);

            // Evaluar con IA
            $evaluation = $this->evaluateWithAI($prompt);

            if (! $evaluation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al evaluar la respuesta',
                ], 500);
            }

            // Guardar puntuación
            $score = $evaluation['score'];
            $this->saveExerciseScore('speaking', $score, $level);

            Log::info('Puntuación de speaking guardada', [
                'score' => $score,
                'level' => $level,
            ]);

            // Generar audio del feedback
            $feedbackText = $evaluation['feedback'];
            $aiController = new \App\Http\Controllers\AIExerciseController;
            $audioUrl = $aiController->generateAudio($feedbackText);

            // Guardar resultado detallado en la nueva tabla
            $this->saveExerciseResult('speaking', $level, $score, $exerciseContent, $userAnswer, $evaluation, $audioUrl);

            Log::info('Audio de feedback generado', [
                'audio_url' => $audioUrl,
            ]);

            return response()->json([
                'success' => true,
                'evaluation' => $evaluation,
                'audio_url' => $audioUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en evaluateSpeaking: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Crea prompt específico para evaluación de speaking
     */
    private function createSpeakingEvaluationPrompt($userAnswer, $exerciseContent, $level)
    {
        $prompt = "Eres un tutor de inglés experto especializado en evaluación de speaking. Evalúa la siguiente respuesta oral de un estudiante de nivel {$level}.

**EJERCICIO DE SPEAKING:**
{$exerciseContent}

**RESPUESTA DEL ESTUDIANTE (transcripción):**
{$userAnswer}

**INSTRUCCIONES:**
1. Evalúa la respuesta del 0 al 100 puntos considerando:
   - Pronunciación y fluidez (30%)
   - Gramática y estructura (35%)
   - Vocabulario y expresión (35%)
2. Proporciona feedback constructivo en español
3. Analiza palabra por palabra si es necesario
4. Menciona fortalezas específicas
5. Sugiere mejoras concretas para pronunciación, gramática y vocabulario
6. Mantén un tono motivador y educativo

**FORMATO DE RESPUESTA (JSON):**
{
    \"score\": [puntuación 0-100],
    \"pronunciation_score\": [puntuación pronunciación 0-100],
    \"grammar_score\": [puntuación gramática 0-100],
    \"vocabulary_score\": [puntuación vocabulario 0-100],
    \"feedback\": \"[feedback detallado en español]\",
    \"strengths\": \"[fortalezas específicas identificadas]\",
    \"improvements\": \"[áreas específicas de mejora]\",
    \"suggestions\": \"[sugerencias concretas para mejorar]\",
    \"word_analysis\": \"[análisis palabra por palabra si es relevante]\"
}

Responde SOLO con el JSON, sin texto adicional.";

        return $prompt;
    }

    /**
     * Crea prompt para evaluación de ejercicios
     */
    private function createEvaluationPrompt($userAnswer, $exerciseContent, $level, $type)
    {
        $typeNames = [
            'vocabulary' => 'vocabulario',
            'grammar' => 'gramática',
            'listening' => 'comprensión auditiva',
        ];

        $typeName = $typeNames[$type] ?? $type;

        $prompt = "Eres un tutor de inglés experto. Evalúa la siguiente respuesta de un estudiante de nivel {$level} en un ejercicio de {$typeName}.

**EJERCICIO:**
{$exerciseContent}

**RESPUESTA DEL ESTUDIANTE:**
{$userAnswer}

**INSTRUCCIONES:**
1. Evalúa la respuesta del 0 al 100 puntos
2. Proporciona feedback constructivo en español
3. Menciona qué hizo bien y qué puede mejorar
4. Da sugerencias específicas para mejorar
5. Mantén un tono motivador y educativo

**FORMATO DE RESPUESTA (JSON):**
{
    \"score\": [puntuación 0-100],
    \"feedback\": \"[feedback detallado en español]\",
    \"strengths\": \"[fortalezas identificadas]\",
    \"improvements\": \"[áreas de mejora]\",
    \"suggestions\": \"[sugerencias específicas]\"
}

Responde SOLO con el JSON, sin texto adicional.";

        return $prompt;
    }

    /**
     * Evalúa respuesta usando OpenAI
     */
    private function evaluateWithAI($prompt)
    {
        try {
            $aiController = new AIExerciseController;
            $credentials = $aiController->getApiCredentials();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('ai.models.tutor'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un tutor de inglés experto que evalúa respuestas de estudiantes. Siempre respondes en formato JSON válido.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => 500,
                'temperature' => 0.3,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? null;

                if ($content) {
                    // Limpiar el contenido para asegurar JSON válido
                    $content = trim($content);
                    $content = preg_replace('/^```json\s*/', '', $content);
                    $content = preg_replace('/\s*```$/', '', $content);

                    $evaluation = json_decode($content, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($evaluation['score'])) {
                        return $evaluation;
                    }
                }
            }

            Log::error('Error en evaluación con IA: '.$response->body());

            return null;

        } catch (\Exception $e) {
            Log::error('Error al evaluar con IA: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Guarda la puntuación del ejercicio
     */
    private function saveExerciseScore($type, $score, $level)
    {
        try {
            // Guardar en sesión
            $sectionScores = session('section_scores', [
                'vocabulary' => 0,
                'grammar' => 0,
                'speaking' => 0,
                'listening' => 0,
            ]);

            // Guardar la puntuación media
            $sectionScores[$type] = $score;

            // Guardar la última puntuación con sufijo _last
            $sectionScores[$type.'_last'] = $score;

            session(['section_scores' => $sectionScores]);

            // Guardar en base de datos si el usuario está autenticado
            if (Auth::check()) {
                // 1. Actualizar tabla user_english_levels (para mantener compatibilidad)
                $userProgress = UserEnglishLevel::where('user_id', Auth::id())
                    ->where('level', $level)
                    ->first();

                if (! $userProgress) {
                    $userProgress = new UserEnglishLevel;
                    $userProgress->user_id = Auth::id();
                    $userProgress->level = $level;
                    $userProgress->section_scores = [];
                }

                $currentSectionScores = $userProgress->section_scores ?? [];
                $currentSectionScores[$type] = $score;
                // También guardar la última puntuación en la base de datos
                $currentSectionScores[$type.'_last'] = $score;

                $userProgress->section_scores = $currentSectionScores;
                $userProgress->score = array_sum(array_filter($currentSectionScores, function ($key) {
                    return ! str_ends_with($key, '_last'); // No sumar las puntuaciones _last
                }, ARRAY_FILTER_USE_KEY));
                $userProgress->evaluated_at = now();
                $userProgress->save();

                Log::info("Puntuación de {$type} guardada: {$score} para usuario ".Auth::id());
            }

        } catch (\Exception $e) {
            Log::error("Error al guardar puntuación de {$type}: ".$e->getMessage());
        }
    }

    /**
     * Guarda un resultado de ejercicio detallado en la nueva tabla
     */
    private function saveExerciseResult($type, $level, $score, $exerciseContent, $userAnswer, $evaluation, $audioUrl = null)
    {
        try {
            // Solo guardar si el usuario está autenticado
            if (Auth::check()) {
                $result = new \App\Models\ExerciseResult;
                $result->user_id = Auth::id();
                $result->level = $level;
                $result->type = $type;
                $result->score = $score;

                // Guardar detalles adicionales según el tipo de ejercicio
                $details = [];
                if (isset($evaluation['strengths'])) {
                    $details['strengths'] = $evaluation['strengths'];
                }
                if (isset($evaluation['improvements'])) {
                    $details['improvements'] = $evaluation['improvements'];
                }
                if (isset($evaluation['suggestions'])) {
                    $details['suggestions'] = $evaluation['suggestions'];
                }
                if (isset($evaluation['pronunciation_score'])) {
                    $details['pronunciation_score'] = $evaluation['pronunciation_score'];
                }
                if (isset($evaluation['grammar_score'])) {
                    $details['grammar_score'] = $evaluation['grammar_score'];
                }
                if (isset($evaluation['vocabulary_score'])) {
                    $details['vocabulary_score'] = $evaluation['vocabulary_score'];
                }
                if (isset($evaluation['word_analysis'])) {
                    $details['word_analysis'] = $evaluation['word_analysis'];
                }

                $result->details = $details;
                $result->feedback = ['text' => $evaluation['feedback'] ?? ''];
                $result->audio_url = $audioUrl;
                $result->exercise_content = $exerciseContent;
                $result->user_answer = $userAnswer;
                $result->save();

                Log::info('Resultado de ejercicio guardado en tabla exercise_results', [
                    'type' => $type,
                    'level' => $level,
                    'score' => $score,
                    'user_id' => Auth::id(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error al guardar resultado de ejercicio: '.$e->getMessage());
        }
    }

    /**
     * Evalúa ejercicios de gramática usando OpenAI
     */
    public function evaluateGrammar(Request $request)
    {
        try {
            $userAnswer = $request->input('answer');
            $exerciseContent = $request->input('exercise_content');
            $level = $request->input('level');

            if (empty($userAnswer) || empty($exerciseContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan datos para la evaluación',
                ], 400);
            }

            // Crear prompt para evaluación
            $prompt = $this->createEvaluationPrompt($userAnswer, $exerciseContent, $level, 'grammar');

            // Evaluar con IA
            $evaluation = $this->evaluateWithAI($prompt);

            if (! $evaluation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al evaluar la respuesta',
                ], 500);
            }

            // Guardar puntuación
            $score = $evaluation['score'];
            $this->saveExerciseScore('grammar', $score, $level);

            // Generar audio del feedback
            $feedbackText = $evaluation['feedback'];
            $aiController = new \App\Http\Controllers\AIExerciseController;
            $audioUrl = $aiController->generateAudio($feedbackText);

            // Guardar resultado detallado en la nueva tabla
            $this->saveExerciseResult('grammar', $level, $score, $exerciseContent, $userAnswer, $evaluation, $audioUrl);

            return response()->json([
                'success' => true,
                'evaluation' => $evaluation,
                'audio_url' => $audioUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en evaluateGrammar: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Evalúa ejercicios de listening usando OpenAI
     */
    public function evaluateListening(Request $request)
    {
        try {
            $userAnswer = $request->input('answer');
            $exerciseContent = $request->input('exercise_content');
            $level = $request->input('level');

            if (empty($userAnswer) || empty($exerciseContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan datos para la evaluación',
                ], 400);
            }

            // Crear prompt para evaluación
            $prompt = $this->createEvaluationPrompt($userAnswer, $exerciseContent, $level, 'listening');

            // Evaluar con IA
            $evaluation = $this->evaluateWithAI($prompt);

            if (! $evaluation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al evaluar la respuesta',
                ], 500);
            }

            // Guardar puntuación
            $score = $evaluation['score'];
            $this->saveExerciseScore('listening', $score, $level);

            // Generar audio del feedback
            $feedbackText = $evaluation['feedback'];
            $aiController = new \App\Http\Controllers\AIExerciseController;
            $audioUrl = $aiController->generateAudio($feedbackText);

            // Guardar resultado detallado en la nueva tabla
            $this->saveExerciseResult('listening', $level, $score, $exerciseContent, $userAnswer, $evaluation, $audioUrl);

            return response()->json([
                'success' => true,
                'evaluation' => $evaluation,
                'audio_url' => $audioUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en evaluateListening: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Evalúa ejercicios de vocabulario usando OpenAI
     */
    public function evaluateVocabulary(Request $request)
    {
        try {
            $userAnswer = $request->input('answer');
            $exerciseContent = $request->input('exercise_content');
            $level = $request->input('level');

            if (empty($userAnswer) || empty($exerciseContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan datos para la evaluación',
                ], 400);
            }

            // Crear prompt para evaluación
            $prompt = $this->createEvaluationPrompt($userAnswer, $exerciseContent, $level, 'vocabulary');

            // Evaluar con IA
            $evaluation = $this->evaluateWithAI($prompt);

            if (! $evaluation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al evaluar la respuesta',
                ], 500);
            }

            // Guardar puntuación
            $score = $evaluation['score'];
            $this->saveExerciseScore('vocabulary', $score, $level);

            // Generar audio del feedback
            $feedbackText = $evaluation['feedback'];
            $aiController = new \App\Http\Controllers\AIExerciseController;
            $audioUrl = $aiController->generateAudio($feedbackText);

            // Guardar resultado detallado en la nueva tabla
            $this->saveExerciseResult('vocabulary', $level, $score, $exerciseContent, $userAnswer, $evaluation, $audioUrl);

            return response()->json([
                'success' => true,
                'evaluation' => $evaluation,
                'audio_url' => $audioUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en evaluateVocabulary: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Muestra la vista de práctica para un tipo específico de ejercicio y nivel
     */
    public function practice($type, $level)
    {
        // Guardar el último tipo de ejercicio realizado en la sesión
        session(['last_exercise_type' => $type]);

        // Validar que el tipo de ejercicio sea válido
        if (! in_array($type, ['vocabulary', 'grammar', 'practice', 'speaking', 'listening'])) {
            return redirect()->route('tutor.exercises', ['level' => $level])
                ->with('error', 'Tipo de ejercicio no válido');
        }

        // Validar que el nivel sea válido
        $validLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        if (! in_array($level, $validLevels)) {
            $level = 'A1'; // Nivel por defecto
        }

        // Generar ejercicio dinámico con IA
        $aiController = new \App\Http\Controllers\AIExerciseController;
        $exerciseResult = $aiController->generateExercise($level, $type);

        $aiExercise = null;
        $audioUrl = null;

        if ($exerciseResult['success']) {
            $aiExercise = $exerciseResult['exercise'];

            // Para ejercicios de speaking y listening, generar audio
            if (
                in_array($type, ['speaking', 'listening']) &&
                isset($aiExercise['audio_text']) &&
                ! empty($aiExercise['audio_text']) &&
                trim($aiExercise['audio_text']) !== ''
            ) {

                $audioUrl = $aiController->generateAudio($aiExercise['audio_text']);
            }
        }

        // Obtener ejercicios estáticos como respaldo
        $exercises = $this->generateExercisesForLevel($level);
        $typeExercises = $exercises[$type] ?? [];

        // Obtener las puntuaciones de sección de la sesión
        $sectionScores = session('section_scores', [
            'vocabulary' => 0,
            'grammar' => 0,
            'speaking' => 0,
            'listening' => 0,
        ]);

        // También obtener puntuaciones de la base de datos si el usuario está autenticado
        if (Auth::check()) {
            $userProgress = UserEnglishLevel::where('user_id', Auth::id())
                ->where('level', $level)
                ->first();

            if ($userProgress && $userProgress->section_scores) {
                // Combinar puntuaciones de base de datos con las de sesión (sesión tiene prioridad)
                $dbScores = $userProgress->section_scores;
                $sectionScores = array_merge($dbScores, $sectionScores);
            }
        }

        // Mapear tipos de ejercicio a secciones para mostrar progreso relevante
        $sectionMapping = [
            'vocabulary' => 'vocabulary',
            'grammar' => 'grammar',
            'practice' => 'speaking',
            'speaking' => 'speaking',
            'listening' => 'listening',
        ];

        $relevantScore = $sectionScores[$sectionMapping[$type]] ?? 0;
        $maxScore = 5; // Cada sección tiene 5 puntos máximo
        $progressPercent = ($relevantScore / $maxScore) * 100;

        // Obtener datos del usuario para mensajes motivacionales
        $user = auth()->user();
        $userLevel = \App\Models\UserEnglishLevel::where('user_id', $user->id)->first();

        // Generar mensaje motivacional personalizado
        $motivationalMessage = $this->generateMotivationalMessage($type, $level, $userLevel);

        // Verificar si venimos de una recomendación de IA
        $aiAnalysis = session('ai_analysis');
        $aiRecommendation = session('ai_recommendation');

        // Datos adicionales para la vista
        $viewData = [
            'level' => $level,
            'type' => $type,
            'exercises' => $typeExercises,
            'aiExercise' => $aiExercise,
            'audioUrl' => $audioUrl,
            'progress' => round($progressPercent),
            'score' => $relevantScore,
            'maxScore' => $maxScore,
            'motivationalMessage' => $motivationalMessage,
            'aiRecommendation' => $aiRecommendation,
            'aiAnalysis' => $aiAnalysis,
        ];

        return view('tutor.practice', $viewData);
    }

    /**
     * Genera ejercicios personalizados basados en el nivel del usuario
     */
    public function generateExercises($level = null)
    {
        // Si no se proporciona nivel, intentamos obtenerlo del usuario autenticado o de la sesión
        if (! $level) {
            if (session()->has('level')) {
                $level = session('level');
            } elseif (Auth::check()) {
                $userLevel = UserEnglishLevel::where('user_id', Auth::id())
                    ->latest('created_at')
                    ->first();

                if ($userLevel) {
                    $level = $userLevel->level;
                }
            }

            // Si aún no tenemos nivel, usamos el predeterminado
            if (! $level) {
                $level = 'A1'; // Nivel por defecto
            }
        }

        // Obtener los ejercicios para el nivel
        $exercises = $this->generateExercisesForLevel($level);

        // Obtener las puntuaciones de sección de la sesión
        $sectionScores = session('section_scores', [
            'vocabulary' => 0,
            'grammar' => 0,
            'speaking' => 0,
            'listening' => 0,
        ]);

        // También obtener puntuaciones de la base de datos si el usuario está autenticado
        if (Auth::check()) {
            $userProgress = UserEnglishLevel::where('user_id', Auth::id())
                ->where('level', $level)
                ->first();

            if ($userProgress && $userProgress->section_scores) {
                // Combinar puntuaciones de base de datos con las de sesión (sesión tiene prioridad)
                $dbScores = $userProgress->section_scores;
                $sectionScores = array_merge($dbScores, $sectionScores);
            }
        }

        // Preparar datos para la vista (la vista espera 'scores', no 'section_scores')
        $scores = $sectionScores;

        // Mostrar la vista con los ejercicios personalizados y puntuaciones
        return view('tutor.exercises', [
            'level' => $level,
            'exercises' => $exercises,
            'scores' => $scores,
            'section_scores' => $sectionScores, // Mantener por compatibilidad
        ]);
    }

    /**
     * Genera ejercicios apropiados para el nivel del usuario
     */
    private function generateExercisesForLevel($level)
    {
        // Ejercicios básicos por nivel - esto es un respaldo si la IA falla
        $exercises = [
            'A1' => [
                'vocabulary' => ['Aprender colores básicos', 'Saludos y presentaciones'],
                'grammar' => ['Verbo "to be"', 'Artículos a/an/the'],
                'speaking' => ['Presentarte en 5 frases', 'Describir tu rutina'],
                'listening' => ['Conversaciones sobre familia', 'Números y fechas'],
            ],
            'A2' => [
                'vocabulary' => ['Comidas y bebidas', 'Adjetivos descriptivos'],
                'grammar' => ['Presente continuo', 'Pasado simple'],
                'speaking' => ['Describir tu fin de semana', 'Pedir comida'],
                'listening' => ['Diálogos de compras', 'Pronósticos del tiempo'],
            ],
            'B1' => [
                'vocabulary' => ['Phrasal verbs', 'Emociones y sentimientos'],
                'grammar' => ['Presente perfecto', 'Condicionales'],
                'speaking' => ['Narrar experiencias', 'Expresar opiniones'],
                'listening' => ['Noticias breves', 'Entrevistas simples'],
            ],
            'B2' => [
                'vocabulary' => ['Vocabulario de negocios', 'Collocations'],
                'grammar' => ['Tiempos narrativos', 'Reported speech'],
                'speaking' => ['Debatir temas', 'Presentaciones'],
                'listening' => ['Conferencias breves', 'Debates'],
            ],
            'C1' => [
                'vocabulary' => ['Vocabulario especializado', 'Sinónimos avanzados'],
                'grammar' => ['Estructuras enfáticas', 'Inversiones'],
                'speaking' => ['Análisis académico', 'Negociación'],
                'listening' => ['Conferencias completas', 'Acentos variados'],
            ],
            'C2' => [
                'vocabulary' => ['Matices de significado', 'Vocabulario literario'],
                'grammar' => ['Construcciones complejas', 'Estructuras arcaicas'],
                'speaking' => ['Análisis crítico', 'Debates académicos'],
                'listening' => ['Literatura audiovisual', 'Jerga y modismos'],
            ],
        ];

        return $exercises[$level] ?? $exercises['A1'];
    }

    /**
     * Genera un mensaje motivacional personalizado para el usuario
     */
    private function generateMotivationalMessage($type, $level, $userLevel)
    {
        $message = '';

        // Si el usuario ha mejorado su nivel, felicitarlo
        if ($userLevel && $userLevel->level !== $level) {
            $message .= '¡Felicidades! Has mejorado tu nivel de inglés a '.$level.'. ';
        }

        // Agregar un mensaje motivacional según el tipo de ejercicio
        switch ($type) {
            case 'vocabulary':
                $message .= 'Recuerda que aprender vocabulario es clave para mejorar tu comprensión y expresión en inglés. ¡Sigue adelante!';
                break;

            case 'grammar':
                $message .= 'La gramática es la base de cualquier idioma. Mantén la práctica y pronto verás mejoras significativas en tu habilidad para comunicarte en inglés.';
                break;

            case 'speaking':
                $message .= 'Hablar en inglés con confianza es un logro increíble. Sigue practicando y no dudes en intentar conversar con otros en inglés.';
                break;

            case 'listening':
                $message .= 'Mejorar tu habilidad para escuchar en inglés te ayudará a entender mejor a los demás y a mejorar tu propio habla. ¡Mantén el ritmo!';
                break;

            default:
                $message .= 'Cada ejercicio que completas te acerca más a tu objetivo de dominar el inglés. ¡No te rindas!';
        }

        return $message;
    }

    /**
     * Genera audio para las instrucciones de los ejercicios
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateInstructionsAudio(Request $request)
    {
        try {
            $text = $request->input('text');

            if (empty($text)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El texto no puede estar vacío',
                ], 400);
            }

            Log::info('Generando audio para instrucciones', [
                'text_length' => strlen($text),
            ]);

            $aiController = new \App\Http\Controllers\AIExerciseController;
            $audioUrl = $aiController->generateAudio($text);

            if (! $audioUrl) {
                throw new \Exception('No se pudo generar el audio');
            }

            Log::info('Audio de instrucciones generado correctamente', [
                'audio_url' => $audioUrl,
            ]);

            return response()->json([
                'success' => true,
                'audio_url' => $audioUrl,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en generateInstructionsAudio: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al generar audio: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera recomendaciones personalizadas del tutor con audio
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateTutorRecommendations(Request $request)
    {
        try {
            // Obtener nivel y puntuaciones del request
            $level = $request->input('level', 'A1');
            $scores = $request->input('scores', []);
            $sectionScores = $request->input('section_scores', []);

            // Combinar puntuaciones
            $allScores = array_merge($scores, $sectionScores);

            // Asegurar que tenemos las cuatro categorías principales
            $categories = ['vocabulary', 'grammar', 'speaking', 'listening'];
            foreach ($categories as $category) {
                if (! isset($allScores[$category])) {
                    $allScores[$category] = 0;
                }
                // Ignorar entradas que terminan en _last
                foreach (array_keys($allScores) as $key) {
                    if (strpos($key, '_last') !== false) {
                        unset($allScores[$key]);
                    }
                }
            }

            // Determinar áreas fuertes y débiles
            $minScore = PHP_INT_MAX;
            $maxScore = 0;
            $weakestArea = 'vocabulary';
            $strongestArea = 'vocabulary';

            foreach ($categories as $category) {
                if (isset($allScores[$category])) {
                    $score = floatval($allScores[$category]);

                    if ($score < $minScore) {
                        $minScore = $score;
                        $weakestArea = $category;
                    }

                    if ($score > $maxScore) {
                        $maxScore = $score;
                        $strongestArea = $category;
                    }
                }
            }

            // Mapeo de nombres en español
            $categoryNames = [
                'vocabulary' => 'vocabulario',
                'grammar' => 'gramática',
                'speaking' => 'conversación',
                'listening' => 'comprensión auditiva',
            ];

            // Generar recomendaciones HTML
            $recommendations = $this->generateRecommendationsHTML($level, $weakestArea, $strongestArea, $allScores, $categoryNames);

            // Generar texto para audio
            $audioText = $this->generateRecommendationsText($level, $weakestArea, $strongestArea, $allScores, $categoryNames);

            // Generar audio con OpenAI
            $aiController = new \App\Http\Controllers\AIExerciseController;
            $audioUrl = $aiController->generateAudio($audioText);

            if (! $audioUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo generar el audio de las recomendaciones',
                ], 500);
            }

            // Determinar el tipo de ejercicio recomendado
            $recommendedType = $weakestArea;

            return response()->json([
                'success' => true,
                'recommendations' => $recommendations,
                'audio_url' => $audioUrl,
                'type' => $recommendedType,
                'type_name' => $categoryNames[$recommendedType] ?? ucfirst($recommendedType),
                'message' => "Basado en tu evaluación, te recomendamos practicar {$categoryNames[$recommendedType]}.",
            ]);

        } catch (\Exception $e) {
            Log::error('Error en generateTutorRecommendations: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al generar recomendaciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera el HTML para las recomendaciones
     */
    private function generateRecommendationsHTML($level, $weakestArea, $strongestArea, $scores, $categoryNames)
    {
        // Recomendaciones específicas por área
        $recommendations = [
            'vocabulary' => [
                'Utiliza tarjetas de memoria para aprender nuevo vocabulario',
                'Etiqueta objetos en tu casa con su nombre en inglés',
                'Lee artículos simples en inglés sobre temas que te interesen',
                'Usa aplicaciones de vocabulario para practicar diariamente',
                'Crea un diccionario personal con palabras nuevas',
            ],
            'grammar' => [
                'Practica con ejercicios de completar frases',
                'Escribe un diario corto diario en inglés',
                'Identifica estructuras gramaticales en textos simples',
                'Utiliza libros de gramática específicos para tu nivel',
                'Haz ejercicios de transformación de oraciones',
            ],
            'speaking' => [
                'Practica conversaciones básicas frente al espejo',
                'Graba tu voz leyendo textos en inglés',
                'Intenta pensar en inglés durante 5 minutos al día',
                'Únete a grupos de conversación en línea',
                'Repite diálogos de series o películas',
            ],
            'listening' => [
                'Escucha podcasts cortos en inglés',
                'Ve videos con subtítulos en inglés',
                'Practica con canciones sencillas en inglés',
                'Utiliza aplicaciones de dictado para mejorar',
                'Escucha audiolibros adaptados a tu nivel',
            ],
        ];

        // Construir el HTML
        $html = '<div class="tutor-recommendations">';

        // Encabezado personalizado
        $html .= '<p class="mb-3">Basado en tu evaluación de nivel <strong>'.$level.'</strong>, he analizado tu progreso:</p>';

        // Tabla de puntuaciones
        $html .= '<div class="table-responsive mb-3">';
        $html .= '<table class="table table-sm table-bordered">';
        $html .= '<thead class="table-light"><tr><th>Área</th><th>Puntuación</th><th>Estado</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($categoryNames as $category => $name) {
            $score = isset($scores[$category]) ? $scores[$category] : 0;
            $status = '';

            if ($category === $weakestArea) {
                $status = '<span class="badge bg-danger">Necesita mejora</span>';
            } elseif ($category === $strongestArea) {
                $status = '<span class="badge bg-success">Fortaleza</span>';
            } else {
                $status = '<span class="badge bg-secondary">Normal</span>';
            }

            $html .= '<tr>';
            $html .= '<td>'.ucfirst($name).'</td>';
            $html .= '<td>'.$score.'</td>';
            $html .= '<td>'.$status.'</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        // Recomendación principal
        $html .= '<div class="alert alert-warning">';
        $html .= '<h5 class="alert-heading"><i class="fas fa-lightbulb me-2"></i>Recomendación Principal</h5>';
        $html .= '<p>Te recomiendo enfocarte en mejorar tu <strong>'.$categoryNames[$weakestArea].'</strong>.</p>';

        // Lista de consejos específicos
        $html .= '<p>Aquí tienes algunos consejos específicos:</p>';
        $html .= '<ul>';
        foreach ($recommendations[$weakestArea] as $tip) {
            $html .= '<li>'.$tip.'</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';

        // Mensaje motivacional
        $html .= '<p class="mt-3">Sigue practicando regularmente y verás mejoras significativas en poco tiempo. ¡Tú puedes hacerlo!</p>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Genera el texto para el audio de las recomendaciones
     */
    private function generateRecommendationsText($level, $weakestArea, $strongestArea, $scores, $categoryNames)
    {
        $text = "Hola. Soy tu tutor personal de inglés. He analizado tu progreso en el nivel {$level} y tengo algunas recomendaciones para ti. ";

        // Análisis de puntuaciones
        $text .= "Basado en tus puntuaciones, he identificado que tu área más fuerte es {$categoryNames[$strongestArea]}. ";
        $text .= "Sin embargo, necesitas trabajar más en {$categoryNames[$weakestArea]}. ";

        // Recomendaciones específicas
        $text .= "Te recomiendo que te enfoques en mejorar tu {$categoryNames[$weakestArea]} con los siguientes ejercicios: ";

        // Recomendaciones según el área débil
        switch ($weakestArea) {
            case 'vocabulary':
                $text .= 'Primero, utiliza tarjetas de memoria para aprender nuevo vocabulario. ';
                $text .= 'Segundo, etiqueta objetos en tu casa con su nombre en inglés. ';
                $text .= 'Tercero, lee artículos simples en inglés sobre temas que te interesen. ';
                break;

            case 'grammar':
                $text .= 'Primero, practica con ejercicios de completar frases. ';
                $text .= 'Segundo, escribe un diario corto diario en inglés. ';
                $text .= 'Tercero, identifica estructuras gramaticales en textos simples. ';
                break;

            case 'speaking':
                $text .= 'Primero, practica conversaciones básicas frente al espejo. ';
                $text .= 'Segundo, graba tu voz leyendo textos en inglés. ';
                $text .= 'Tercero, intenta pensar en inglés durante 5 minutos al día. ';
                break;

            case 'listening':
                $text .= 'Primero, escucha podcasts cortos en inglés. ';
                $text .= 'Segundo, ve videos con subtítulos en inglés. ';
                $text .= 'Tercero, practica con canciones sencillas en inglés. ';
                break;
        }

        // Mensaje motivacional
        $text .= 'Recuerda que la consistencia es clave para aprender un idioma. ';
        $text .= 'Te sugiero que practiques al menos 15 minutos diarios y verás mejoras significativas en poco tiempo. ';
        $text .= '¡Sigue adelante! Estoy aquí para ayudarte en tu proceso de aprendizaje.';

        return $text;
    }

    /**
     * Mostrar el curso de inglés específico
     */
    public function englishCourse()
    {
        $user = auth()->user();

        // Obtener el nivel de inglés del usuario o usar A1 por defecto
        $userLevel = \App\Models\UserEnglishLevel::where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        $level = $userLevel ? $userLevel->level : 'A1';

        // Guardar el nivel en la sesión para usarlo en la vista
        session(['level' => $level]);

        // Si hay puntuaciones de secciones en el modelo, guardarlas en la sesión
        if ($userLevel && isset($userLevel->section_scores)) {
            session(['section_scores' => $userLevel->section_scores]);
        }

        $englishProgress = [
            'level' => $level,
            'listening_score' => session('listening_score', $userLevel->listening_score ?? 0),
            'vocabulary_score' => session('vocabulary_score', $userLevel->vocabulary_score ?? 0),
            'grammar_score' => session('grammar_score', $userLevel->grammar_score ?? 0),
            'speaking_score' => session('speaking_score', $userLevel->speaking_score ?? 0),
        ];

        return view('tutor.english', compact('englishProgress', 'level'));
    }
}
