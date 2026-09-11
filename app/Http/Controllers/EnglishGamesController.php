<?php

namespace App\Http\Controllers;

use App\Http\Traits\TracksUserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnglishGamesController extends Controller
{
    use TracksUserActivity;

    /**
     * Mostrar menú de juegos de inglés
     */
    public function index()
    {
        $user = Auth::user();
        $progress = $user->gameProgress;

        // Obtener estadísticas de juegos de inglés
        $englishStats = DB::table('english_game_sessions')
            ->where('user_id', $user->id)
            ->select(
                'game_type',
                DB::raw('COUNT(*) as sessions_played'),
                DB::raw('SUM(score) as total_score'),
                DB::raw('MAX(score) as best_score'),
                DB::raw('AVG(accuracy) as avg_accuracy')
            )
            ->groupBy('game_type')
            ->get()
            ->keyBy('game_type');

        return view('tutor.games.index', compact('progress', 'englishStats'));
    }

    /**
     * Word Match Rush - Emparejar palabras
     */
    public function wordMatchRush()
    {
        $user = Auth::user();
        $level = $user->gameProgress->level ?? 1;

        // Obtener vocabulario según nivel
        $vocabulary = $this->getVocabularyByLevel($level);

        return view('tutor.games.word-match-rush', compact('vocabulary', 'level'));
    }

    /**
     * Sentence Builder - Construir oraciones
     */
    public function sentenceBuilder()
    {
        $user = Auth::user();
        $level = $user->gameProgress->level ?? 1;

        // Obtener oraciones según nivel
        $sentences = $this->getSentencesByLevel($level);

        return view('tutor.games.sentence-builder', compact('sentences', 'level'));
    }

    /**
     * Vocabulary Shooter - Disparar traducciones
     */
    public function vocabularyShooter()
    {
        $user = Auth::user();
        $level = $user->gameProgress->level ?? 1;

        // Obtener palabras según nivel
        $words = $this->getVocabularyByLevel($level);

        return view('tutor.games.vocabulary-shooter', compact('words', 'level'));
    }

    /**
     * Grammar Runner - Plataformas gramaticales
     */
    public function grammarRunner()
    {
        $user = Auth::user();
        $level = $user->gameProgress->level ?? 1;

        // Obtener preguntas de gramática según nivel
        $grammarQuestions = $this->getGrammarQuestionsByLevel($level);

        return view('tutor.games.grammar-runner', compact('grammarQuestions', 'level'));
    }

    /**
     * Listening Challenge - Desafío de escucha
     */
    public function listeningChallenge()
    {
        $user = Auth::user();
        $level = $user->gameProgress->level ?? 1;

        return view('tutor.games.listening-challenge', compact('level'));
    }

    /**
     * Obtener ejercicios de Listening Challenge (API)
     */
    public function getListeningChallengeExercises()
    {
        $user = Auth::user();
        $level = $user->gameProgress->level ?? 1;

        $exercises = $this->getListeningExercisesByLevel($level);

        // Mezclar ejercicios aleatoriamente
        shuffle($exercises);

        return response()->json([
            'success' => true,
            'exercises' => $exercises,
            'level' => $level,
        ]);
    }

    /**
     * Guardar resultado del juego
     */
    public function saveGameResult(Request $request)
    {
        // Permitir que 'level' llegue como string de dificultad (basic/intermediate/advanced) y normalizarlo a int
        $payload = $request->all();
        if (isset($payload['level']) && is_string($payload['level'])) {
            $map = [
                'basic' => 1,
                'intermediate' => 2,
                'advanced' => 3,
            ];
            $payload['level'] = $map[strtolower($payload['level'])] ?? 1;
            $request->merge(['level' => $payload['level']]);
        }

        $validated = $request->validate([
            'game_type' => 'required|string',
            'score' => 'required|integer',
            'accuracy' => 'required|numeric|min:0|max:100',
            'time_spent' => 'required|integer',
            'correct_answers' => 'required|integer',
            'total_questions' => 'required|integer',
            'level' => 'required|integer',
        ]);

        $user = Auth::user();

        // Guardar sesión de juego
        $sessionId = DB::table('english_game_sessions')->insertGetId([
            'user_id' => $user->id,
            'game_type' => $validated['game_type'],
            'score' => $validated['score'],
            'accuracy' => $validated['accuracy'],
            'time_spent' => $validated['time_spent'],
            'correct_answers' => $validated['correct_answers'],
            'total_questions' => $validated['total_questions'],
            'level' => $validated['level'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Calcular XP ganado basado en rendimiento
        $xpEarned = $this->calculateXP($validated);

        // Actualizar progreso del usuario
        $progress = $user->gameProgress;
        $progress->addXP($xpEarned);
        $progress->updateActivity('english_game');

        // Registrar actividad en el tracker para el engagement dashboard
        $this->trackActivity(
            $user->id,
            'english_games',
            $validated['time_spent'],
            $xpEarned,
            $this->determineActivityQualityEnum($validated['accuracy'], $validated['time_spent'])
        );

        // Verificar logros
        $this->checkAchievements($user, $validated);

        // Refrescar el modelo para obtener los valores actualizados
        $progress->refresh();

        return response()->json([
            'success' => true,
            'xp_earned' => $xpEarned,
            'total_xp' => $progress->total_xp,
            'level' => $progress->level,
            'current_xp' => $progress->current_xp,
            'required_xp' => $progress->required_xp,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Obtener palabras para Vocabulary Shooter (API)
     */
    public function getVocabularyShooterWords()
    {
        $allWords = array_merge(
            $this->getShooterBasicWords(),
            $this->getShooterIntermediateWords(),
            $this->getShooterAdvancedWords()
        );

        return response()->json([
            'success' => true,
            'words' => $allWords,
        ]);
    }

    /**
     * Palabras básicas para Vocabulary Shooter
     */
    private function getShooterBasicWords()
    {
        return [
            ['spanish' => 'casa', 'english' => 'house', 'level' => 'basic'],
            ['spanish' => 'perro', 'english' => 'dog', 'level' => 'basic'],
            ['spanish' => 'gato', 'english' => 'cat', 'level' => 'basic'],
            ['spanish' => 'carro', 'english' => 'car', 'level' => 'basic'],
            ['spanish' => 'libro', 'english' => 'book', 'level' => 'basic'],
            ['spanish' => 'agua', 'english' => 'water', 'level' => 'basic'],
            ['spanish' => 'sol', 'english' => 'sun', 'level' => 'basic'],
            ['spanish' => 'luna', 'english' => 'moon', 'level' => 'basic'],
            ['spanish' => 'árbol', 'english' => 'tree', 'level' => 'basic'],
            ['spanish' => 'flor', 'english' => 'flower', 'level' => 'basic'],
            ['spanish' => 'pájaro', 'english' => 'bird', 'level' => 'basic'],
            ['spanish' => 'pez', 'english' => 'fish', 'level' => 'basic'],
            ['spanish' => 'comida', 'english' => 'food', 'level' => 'basic'],
            ['spanish' => 'mesa', 'english' => 'table', 'level' => 'basic'],
            ['spanish' => 'silla', 'english' => 'chair', 'level' => 'basic'],
            ['spanish' => 'puerta', 'english' => 'door', 'level' => 'basic'],
            ['spanish' => 'ventana', 'english' => 'window', 'level' => 'basic'],
            ['spanish' => 'teléfono', 'english' => 'phone', 'level' => 'basic'],
            ['spanish' => 'computadora', 'english' => 'computer', 'level' => 'basic'],
            ['spanish' => 'bolígrafo', 'english' => 'pen', 'level' => 'basic'],
            ['spanish' => 'lápiz', 'english' => 'pencil', 'level' => 'basic'],
            ['spanish' => 'bolsa', 'english' => 'bag', 'level' => 'basic'],
            ['spanish' => 'zapatos', 'english' => 'shoes', 'level' => 'basic'],
            ['spanish' => 'camisa', 'english' => 'shirt', 'level' => 'basic'],
            ['spanish' => 'manzana', 'english' => 'apple', 'level' => 'basic'],
            ['spanish' => 'naranja', 'english' => 'orange', 'level' => 'basic'],
            ['spanish' => 'plátano', 'english' => 'banana', 'level' => 'basic'],
            ['spanish' => 'leche', 'english' => 'milk', 'level' => 'basic'],
            ['spanish' => 'pan', 'english' => 'bread', 'level' => 'basic'],
            ['spanish' => 'queso', 'english' => 'cheese', 'level' => 'basic'],
        ];
    }

    /**
     * Palabras intermedias para Vocabulary Shooter
     */
    private function getShooterIntermediateWords()
    {
        return [
            ['spanish' => 'desafío', 'english' => 'challenge', 'level' => 'intermediate'],
            ['spanish' => 'conocimiento', 'english' => 'knowledge', 'level' => 'intermediate'],
            ['spanish' => 'amistad', 'english' => 'friendship', 'level' => 'intermediate'],
            ['spanish' => 'aventura', 'english' => 'adventure', 'level' => 'intermediate'],
            ['spanish' => 'mejorar', 'english' => 'improve', 'level' => 'intermediate'],
            ['spanish' => 'descubrir', 'english' => 'discover', 'level' => 'intermediate'],
            ['spanish' => 'crear', 'english' => 'create', 'level' => 'intermediate'],
            ['spanish' => 'entender', 'english' => 'understand', 'level' => 'intermediate'],
            ['spanish' => 'solución', 'english' => 'solution', 'level' => 'intermediate'],
            ['spanish' => 'comunicar', 'english' => 'communicate', 'level' => 'intermediate'],
            ['spanish' => 'importante', 'english' => 'important', 'level' => 'intermediate'],
            ['spanish' => 'diferente', 'english' => 'different', 'level' => 'intermediate'],
            ['spanish' => 'necesario', 'english' => 'necessary', 'level' => 'intermediate'],
            ['spanish' => 'posible', 'english' => 'possible', 'level' => 'intermediate'],
            ['spanish' => 'interesante', 'english' => 'interesting', 'level' => 'intermediate'],
            ['spanish' => 'ambiente', 'english' => 'environment', 'level' => 'intermediate'],
            ['spanish' => 'desarrollo', 'english' => 'development', 'level' => 'intermediate'],
            ['spanish' => 'experiencia', 'english' => 'experience', 'level' => 'intermediate'],
            ['spanish' => 'decisión', 'english' => 'decision', 'level' => 'intermediate'],
            ['spanish' => 'información', 'english' => 'information', 'level' => 'intermediate'],
        ];
    }

    /**
     * Palabras avanzadas para Vocabulary Shooter
     */
    private function getShooterAdvancedWords()
    {
        return [
            ['spanish' => 'lograr', 'english' => 'accomplish', 'level' => 'advanced'],
            ['spanish' => 'percibir', 'english' => 'perceive', 'level' => 'advanced'],
            ['spanish' => 'contemplar', 'english' => 'contemplate', 'level' => 'advanced'],
            ['spanish' => 'esfuerzo', 'english' => 'endeavor', 'level' => 'advanced'],
            ['spanish' => 'profundo', 'english' => 'profound', 'level' => 'advanced'],
            ['spanish' => 'persuadir', 'english' => 'persuade', 'level' => 'advanced'],
            ['spanish' => 'elocuente', 'english' => 'eloquent', 'level' => 'advanced'],
            ['spanish' => 'intrincado', 'english' => 'intricate', 'level' => 'advanced'],
            ['spanish' => 'resiliente', 'english' => 'resilient', 'level' => 'advanced'],
            ['spanish' => 'innovador', 'english' => 'innovative', 'level' => 'advanced'],
            ['spanish' => 'elaborar', 'english' => 'elaborate', 'level' => 'advanced'],
            ['spanish' => 'sofisticado', 'english' => 'sophisticated', 'level' => 'advanced'],
            ['spanish' => 'ambiguo', 'english' => 'ambiguous', 'level' => 'advanced'],
            ['spanish' => 'coherente', 'english' => 'coherent', 'level' => 'advanced'],
            ['spanish' => 'meticuloso', 'english' => 'meticulous', 'level' => 'advanced'],
            ['spanish' => 'pragmático', 'english' => 'pragmatic', 'level' => 'advanced'],
            ['spanish' => 'efímero', 'english' => 'ephemeral', 'level' => 'advanced'],
            ['spanish' => 'benevolente', 'english' => 'benevolent', 'level' => 'advanced'],
            ['spanish' => 'circunspecto', 'english' => 'circumspect', 'level' => 'advanced'],
            ['spanish' => 'diligente', 'english' => 'diligent', 'level' => 'advanced'],
        ];
    }

    /**
     * Obtener vocabulario por nivel
     */
    private function getVocabularyByLevel($level)
    {
        // Niveles 1-5: Vocabulario básico
        // Niveles 6-10: Vocabulario intermedio
        // Niveles 11+: Vocabulario avanzado

        if ($level <= 5) {
            return $this->getBasicVocabulary();
        } elseif ($level <= 10) {
            return $this->getIntermediateVocabulary();
        } else {
            return $this->getAdvancedVocabulary();
        }
    }

    /**
     * Vocabulario básico
     */
    private function getBasicVocabulary()
    {
        return [
            ['word' => 'cat', 'translation' => 'gato', 'image' => '🐱'],
            ['word' => 'dog', 'translation' => 'perro', 'image' => '🐶'],
            ['word' => 'house', 'translation' => 'casa', 'image' => '🏠'],
            ['word' => 'car', 'translation' => 'carro', 'image' => '🚗'],
            ['word' => 'book', 'translation' => 'libro', 'image' => '📚'],
            ['word' => 'apple', 'translation' => 'manzana', 'image' => '🍎'],
            ['word' => 'water', 'translation' => 'agua', 'image' => '💧'],
            ['word' => 'sun', 'translation' => 'sol', 'image' => '☀️'],
            ['word' => 'moon', 'translation' => 'luna', 'image' => '🌙'],
            ['word' => 'tree', 'translation' => 'árbol', 'image' => '🌳'],
            ['word' => 'flower', 'translation' => 'flor', 'image' => '🌸'],
            ['word' => 'bird', 'translation' => 'pájaro', 'image' => '🐦'],
            ['word' => 'fish', 'translation' => 'pez', 'image' => '🐟'],
            ['word' => 'food', 'translation' => 'comida', 'image' => '🍔'],
            ['word' => 'table', 'translation' => 'mesa', 'image' => '🪑'],
            ['word' => 'chair', 'translation' => 'silla', 'image' => '🪑'],
            ['word' => 'door', 'translation' => 'puerta', 'image' => '🚪'],
            ['word' => 'window', 'translation' => 'ventana', 'image' => '🪟'],
            ['word' => 'phone', 'translation' => 'teléfono', 'image' => '📱'],
            ['word' => 'computer', 'translation' => 'computadora', 'image' => '💻'],
            ['word' => 'pen', 'translation' => 'bolígrafo', 'image' => '🖊️'],
            ['word' => 'pencil', 'translation' => 'lápiz', 'image' => '✏️'],
            ['word' => 'bag', 'translation' => 'bolsa', 'image' => '👜'],
            ['word' => 'shoes', 'translation' => 'zapatos', 'image' => '👟'],
            ['word' => 'shirt', 'translation' => 'camisa', 'image' => '👕'],
        ];
    }

    /**
     * Vocabulario intermedio
     */
    private function getIntermediateVocabulary()
    {
        return [
            ['word' => 'challenge', 'translation' => 'desafío', 'image' => '🎯'],
            ['word' => 'knowledge', 'translation' => 'conocimiento', 'image' => '🧠'],
            ['word' => 'friendship', 'translation' => 'amistad', 'image' => '🤝'],
            ['word' => 'adventure', 'translation' => 'aventura', 'image' => '🗺️'],
            ['word' => 'improve', 'translation' => 'mejorar', 'image' => '📈'],
            ['word' => 'discover', 'translation' => 'descubrir', 'image' => '🔍'],
            ['word' => 'create', 'translation' => 'crear', 'image' => '✨'],
            ['word' => 'understand', 'translation' => 'entender', 'image' => '💡'],
            ['word' => 'solution', 'translation' => 'solución', 'image' => '🔑'],
            ['word' => 'communicate', 'translation' => 'comunicar', 'image' => '💬'],
        ];
    }

    /**
     * Vocabulario avanzado
     */
    private function getAdvancedVocabulary()
    {
        return [
            ['word' => 'accomplish', 'translation' => 'lograr', 'image' => '🏆'],
            ['word' => 'perceive', 'translation' => 'percibir', 'image' => '👁️'],
            ['word' => 'contemplate', 'translation' => 'contemplar', 'image' => '🤔'],
            ['word' => 'endeavor', 'translation' => 'esfuerzo', 'image' => '💪'],
            ['word' => 'profound', 'translation' => 'profundo', 'image' => '🌊'],
            ['word' => 'persuade', 'translation' => 'persuadir', 'image' => '🗣️'],
            ['word' => 'eloquent', 'translation' => 'elocuente', 'image' => '🎭'],
            ['word' => 'intricate', 'translation' => 'intrincado', 'image' => '🧩'],
            ['word' => 'resilient', 'translation' => 'resiliente', 'image' => '🛡️'],
            ['word' => 'innovative', 'translation' => 'innovador', 'image' => '💡'],
        ];
    }

    /**
     * Obtener oraciones por nivel
     */
    private function getSentencesByLevel($level)
    {
        if ($level <= 5) {
            return [
                ['words' => ['I', 'am', 'a', 'student'], 'correct' => 'I am a student', 'translation' => 'Yo soy un estudiante'],
                ['words' => ['She', 'likes', 'cats'], 'correct' => 'She likes cats', 'translation' => 'A ella le gustan los gatos'],
                ['words' => ['We', 'play', 'soccer'], 'correct' => 'We play soccer', 'translation' => 'Nosotros jugamos fútbol'],
                ['words' => ['The', 'book', 'is', 'red'], 'correct' => 'The book is red', 'translation' => 'El libro es rojo'],
                ['words' => ['He', 'eats', 'breakfast'], 'correct' => 'He eats breakfast', 'translation' => 'Él desayuna'],
                ['words' => ['They', 'are', 'friends'], 'correct' => 'They are friends', 'translation' => 'Ellos son amigos'],
                ['words' => ['My', 'dog', 'is', 'big'], 'correct' => 'My dog is big', 'translation' => 'Mi perro es grande'],
                ['words' => ['I', 'like', 'music'], 'correct' => 'I like music', 'translation' => 'Me gusta la música'],
                ['words' => ['The', 'cat', 'sleeps', 'here'], 'correct' => 'The cat sleeps here', 'translation' => 'El gato duerme aquí'],
                ['words' => ['We', 'study', 'English'], 'correct' => 'We study English', 'translation' => 'Nosotros estudiamos inglés'],
            ];
        } elseif ($level <= 10) {
            return [
                ['words' => ['I', 'have', 'been', 'learning', 'English'], 'correct' => 'I have been learning English', 'translation' => 'He estado aprendiendo inglés'],
                ['words' => ['They', 'will', 'arrive', 'tomorrow'], 'correct' => 'They will arrive tomorrow', 'translation' => 'Ellos llegarán mañana'],
                ['words' => ['She', 'has', 'finished', 'her', 'homework'], 'correct' => 'She has finished her homework', 'translation' => 'Ella ha terminado su tarea'],
                ['words' => ['We', 'should', 'practice', 'more', 'often'], 'correct' => 'We should practice more often', 'translation' => 'Deberíamos practicar más seguido'],
                ['words' => ['He', 'would', 'like', 'to', 'travel'], 'correct' => 'He would like to travel', 'translation' => 'A él le gustaría viajar'],
                ['words' => ['I', 'am', 'going', 'to', 'the', 'store'], 'correct' => 'I am going to the store', 'translation' => 'Voy a la tienda'],
                ['words' => ['They', 'were', 'watching', 'a', 'movie'], 'correct' => 'They were watching a movie', 'translation' => 'Ellos estaban viendo una película'],
                ['words' => ['She', 'can', 'speak', 'three', 'languages'], 'correct' => 'She can speak three languages', 'translation' => 'Ella puede hablar tres idiomas'],
                ['words' => ['We', 'must', 'finish', 'this', 'today'], 'correct' => 'We must finish this today', 'translation' => 'Debemos terminar esto hoy'],
                ['words' => ['He', 'might', 'come', 'to', 'the', 'party'], 'correct' => 'He might come to the party', 'translation' => 'Él podría venir a la fiesta'],
            ];
        } else {
            return [
                ['words' => ['Had', 'I', 'known', 'earlier', 'I', 'would', 'have', 'helped'], 'correct' => 'Had I known earlier I would have helped', 'translation' => 'Si lo hubiera sabido antes, habría ayudado'],
                ['words' => ['The', 'more', 'you', 'practice', 'the', 'better', 'you', 'become'], 'correct' => 'The more you practice the better you become', 'translation' => 'Cuanto más practicas, mejor te vuelves'],
                ['words' => ['Despite', 'the', 'difficulties', 'we', 'succeeded'], 'correct' => 'Despite the difficulties we succeeded', 'translation' => 'A pesar de las dificultades, tuvimos éxito'],
                ['words' => ['She', 'would', 'rather', 'stay', 'home', 'than', 'go', 'out'], 'correct' => 'She would rather stay home than go out', 'translation' => 'Ella preferiría quedarse en casa que salir'],
                ['words' => ['Not', 'only', 'is', 'he', 'smart', 'but', 'also', 'kind'], 'correct' => 'Not only is he smart but also kind', 'translation' => 'No solo es inteligente sino también amable'],
                ['words' => ['By', 'the', 'time', 'you', 'arrive', 'I', 'will', 'have', 'finished'], 'correct' => 'By the time you arrive I will have finished', 'translation' => 'Para cuando llegues, habré terminado'],
                ['words' => ['Were', 'it', 'not', 'for', 'your', 'help', 'I', 'would', 'fail'], 'correct' => 'Were it not for your help I would fail', 'translation' => 'Si no fuera por tu ayuda, fallaría'],
                ['words' => ['Seldom', 'have', 'I', 'seen', 'such', 'dedication'], 'correct' => 'Seldom have I seen such dedication', 'translation' => 'Rara vez he visto tal dedicación'],
                ['words' => ['Little', 'did', 'she', 'know', 'what', 'awaited', 'her'], 'correct' => 'Little did she know what awaited her', 'translation' => 'Poco sabía ella lo que le esperaba'],
                ['words' => ['The', 'sooner', 'we', 'start', 'the', 'better'], 'correct' => 'The sooner we start the better', 'translation' => 'Cuanto antes empecemos, mejor'],
            ];
        }
    }

    /**
     * Obtener preguntas de gramática por nivel
     */
    /**
     * API endpoint para obtener preguntas de Grammar Runner
     */
    public function getGrammarRunnerQuestions()
    {
        $user = Auth::user();
        $level = $user->level ?? 1;

        // Determinar dificultad basado en nivel del usuario
        $difficulty = 'basic';
        if ($level >= 6 && $level <= 10) {
            $difficulty = 'intermediate';
        } elseif ($level > 10) {
            $difficulty = 'advanced';
        }

        // Obtener preguntas por dificultad
        $allQuestions = $this->getAllGrammarQuestions();
        $filteredQuestions = array_filter($allQuestions, function ($q) use ($difficulty) {
            return $q['level'] === $difficulty;
        });

        return response()->json([
            'success' => true,
            'questions' => array_values($filteredQuestions),
            'difficulty' => $difficulty,
        ]);
    }

    /**
     * Todas las preguntas de gramática
     */
    private function getAllGrammarQuestions()
    {
        return array_merge(
            $this->getBasicGrammarQuestions(),
            $this->getIntermediateGrammarQuestions(),
            $this->getAdvancedGrammarQuestions()
        );
    }

    /**
     * Preguntas básicas de gramática
     */
    private function getBasicGrammarQuestions()
    {
        return [
            // Present Simple
            ['question' => 'She ___ to school every day.', 'options' => ['go', 'goes', 'going', 'gone'], 'correct' => 1, 'level' => 'basic', 'category' => 'present_simple'],
            ['question' => 'I ___ English at home.', 'options' => ['study', 'studies', 'studying', 'studied'], 'correct' => 0, 'level' => 'basic', 'category' => 'present_simple'],
            ['question' => 'They ___ soccer on weekends.', 'options' => ['play', 'plays', 'playing', 'played'], 'correct' => 0, 'level' => 'basic', 'category' => 'present_simple'],
            ['question' => 'He ___ breakfast at 7 AM.', 'options' => ['eat', 'eats', 'eating', 'eaten'], 'correct' => 1, 'level' => 'basic', 'category' => 'present_simple'],
            ['question' => 'We ___ TV in the evening.', 'options' => ['watch', 'watches', 'watching', 'watched'], 'correct' => 0, 'level' => 'basic', 'category' => 'present_simple'],

            // Articles
            ['question' => 'I have ___ apple.', 'options' => ['a', 'an', 'the', '-'], 'correct' => 1, 'level' => 'basic', 'category' => 'articles'],
            ['question' => 'She is ___ teacher.', 'options' => ['a', 'an', 'the', '-'], 'correct' => 0, 'level' => 'basic', 'category' => 'articles'],
            ['question' => '___ sun is bright today.', 'options' => ['A', 'An', 'The', '-'], 'correct' => 2, 'level' => 'basic', 'category' => 'articles'],
            ['question' => 'He needs ___ umbrella.', 'options' => ['a', 'an', 'the', '-'], 'correct' => 1, 'level' => 'basic', 'category' => 'articles'],
            ['question' => 'I want ___ orange juice.', 'options' => ['a', 'an', 'the', '-'], 'correct' => 3, 'level' => 'basic', 'category' => 'articles'],

            // Pronouns
            ['question' => '___ is my friend.', 'options' => ['He', 'Him', 'His', 'Her'], 'correct' => 0, 'level' => 'basic', 'category' => 'pronouns'],
            ['question' => 'This book is ___.', 'options' => ['my', 'mine', 'me', 'I'], 'correct' => 1, 'level' => 'basic', 'category' => 'pronouns'],
            ['question' => 'Can you help ___?', 'options' => ['I', 'me', 'my', 'mine'], 'correct' => 1, 'level' => 'basic', 'category' => 'pronouns'],
            ['question' => '___ are students.', 'options' => ['They', 'Them', 'Their', 'Theirs'], 'correct' => 0, 'level' => 'basic', 'category' => 'pronouns'],
            ['question' => 'That car is ___.', 'options' => ['her', 'hers', 'she', 'his'], 'correct' => 1, 'level' => 'basic', 'category' => 'pronouns'],

            // Prepositions
            ['question' => 'The book is ___ the table.', 'options' => ['in', 'on', 'at', 'to'], 'correct' => 1, 'level' => 'basic', 'category' => 'prepositions'],
            ['question' => 'I live ___ Mexico.', 'options' => ['in', 'on', 'at', 'to'], 'correct' => 0, 'level' => 'basic', 'category' => 'prepositions'],
            ['question' => 'She arrives ___ 8 o\'clock.', 'options' => ['in', 'on', 'at', 'to'], 'correct' => 2, 'level' => 'basic', 'category' => 'prepositions'],
            ['question' => 'We go ___ school by bus.', 'options' => ['in', 'on', 'at', 'to'], 'correct' => 3, 'level' => 'basic', 'category' => 'prepositions'],
            ['question' => 'The party is ___ Saturday.', 'options' => ['in', 'on', 'at', 'to'], 'correct' => 1, 'level' => 'basic', 'category' => 'prepositions'],
        ];
    }

    /**
     * Preguntas intermedias de gramática
     */
    private function getIntermediateGrammarQuestions()
    {
        return [
            // Present Continuous
            ['question' => 'They ___ playing soccer now.', 'options' => ['is', 'am', 'are', 'be'], 'correct' => 2, 'level' => 'intermediate', 'category' => 'present_continuous'],
            ['question' => 'I ___ studying English at the moment.', 'options' => ['is', 'am', 'are', 'be'], 'correct' => 1, 'level' => 'intermediate', 'category' => 'present_continuous'],
            ['question' => 'She ___ working on her project.', 'options' => ['is', 'am', 'are', 'be'], 'correct' => 0, 'level' => 'intermediate', 'category' => 'present_continuous'],
            ['question' => 'We ___ not watching TV right now.', 'options' => ['is', 'am', 'are', 'be'], 'correct' => 2, 'level' => 'intermediate', 'category' => 'present_continuous'],

            // Past Simple
            ['question' => 'I ___ to the park yesterday.', 'options' => ['go', 'goes', 'went', 'going'], 'correct' => 2, 'level' => 'intermediate', 'category' => 'past_simple'],
            ['question' => 'She ___ her homework last night.', 'options' => ['do', 'does', 'did', 'doing'], 'correct' => 2, 'level' => 'intermediate', 'category' => 'past_simple'],
            ['question' => 'They ___ a movie last weekend.', 'options' => ['watch', 'watches', 'watched', 'watching'], 'correct' => 2, 'level' => 'intermediate', 'category' => 'past_simple'],
            ['question' => 'We ___ pizza for dinner.', 'options' => ['have', 'has', 'had', 'having'], 'correct' => 2, 'level' => 'intermediate', 'category' => 'past_simple'],

            // Present Perfect
            ['question' => 'I ___ seen that movie before.', 'options' => ['have', 'has', 'had', 'having'], 'correct' => 0, 'level' => 'intermediate', 'category' => 'present_perfect'],
            ['question' => 'She ___ finished her work.', 'options' => ['have', 'has', 'had', 'having'], 'correct' => 1, 'level' => 'intermediate', 'category' => 'present_perfect'],
            ['question' => 'They ___ already eaten lunch.', 'options' => ['have', 'has', 'had', 'having'], 'correct' => 0, 'level' => 'intermediate', 'category' => 'present_perfect'],
            ['question' => 'He ___ never been to Paris.', 'options' => ['have', 'has', 'had', 'having'], 'correct' => 1, 'level' => 'intermediate', 'category' => 'present_perfect'],

            // Future Simple
            ['question' => 'I ___ visit my grandmother tomorrow.', 'options' => ['will', 'would', 'shall', 'should'], 'correct' => 0, 'level' => 'intermediate', 'category' => 'future_simple'],
            ['question' => 'They ___ not come to the party.', 'options' => ['will', 'would', 'shall', 'should'], 'correct' => 0, 'level' => 'intermediate', 'category' => 'future_simple'],
            ['question' => 'She ___ probably call you later.', 'options' => ['will', 'would', 'shall', 'should'], 'correct' => 0, 'level' => 'intermediate', 'category' => 'future_simple'],

            // Comparatives and Superlatives
            ['question' => 'This book is ___ than that one.', 'options' => ['good', 'better', 'best', 'well'], 'correct' => 1, 'level' => 'intermediate', 'category' => 'comparatives'],
            ['question' => 'She is the ___ student in class.', 'options' => ['smart', 'smarter', 'smartest', 'more smart'], 'correct' => 2, 'level' => 'intermediate', 'category' => 'superlatives'],
            ['question' => 'This is ___ expensive than I thought.', 'options' => ['more', 'most', 'much', 'many'], 'correct' => 0, 'level' => 'intermediate', 'category' => 'comparatives'],

            // Modal Verbs
            ['question' => 'You ___ study harder for the exam.', 'options' => ['should', 'can', 'may', 'will'], 'correct' => 0, 'level' => 'intermediate', 'category' => 'modals'],
            ['question' => 'I ___ speak three languages.', 'options' => ['should', 'can', 'may', 'must'], 'correct' => 1, 'level' => 'intermediate', 'category' => 'modals'],
            ['question' => '___ I borrow your pen?', 'options' => ['Should', 'Can', 'Must', 'Will'], 'correct' => 1, 'level' => 'intermediate', 'category' => 'modals'],
        ];
    }

    /**
     * Preguntas avanzadas de gramática
     */
    private function getAdvancedGrammarQuestions()
    {
        return [
            // Past Perfect
            ['question' => 'By the time I arrived, they ___ already left.', 'options' => ['have', 'has', 'had', 'having'], 'correct' => 2, 'level' => 'advanced', 'category' => 'past_perfect'],
            ['question' => 'She ___ never seen such a beautiful place before.', 'options' => ['have', 'has', 'had', 'having'], 'correct' => 2, 'level' => 'advanced', 'category' => 'past_perfect'],
            ['question' => 'We ___ finished dinner when he called.', 'options' => ['have', 'has', 'had', 'having'], 'correct' => 2, 'level' => 'advanced', 'category' => 'past_perfect'],

            // Conditional Sentences
            ['question' => 'If I ___ rich, I would travel the world.', 'options' => ['am', 'was', 'were', 'be'], 'correct' => 2, 'level' => 'advanced', 'category' => 'conditionals'],
            ['question' => 'If it ___ tomorrow, we will cancel the trip.', 'options' => ['rain', 'rains', 'rained', 'raining'], 'correct' => 1, 'level' => 'advanced', 'category' => 'conditionals'],
            ['question' => 'I ___ have helped if you had asked.', 'options' => ['will', 'would', 'shall', 'should'], 'correct' => 1, 'level' => 'advanced', 'category' => 'conditionals'],

            // Passive Voice
            ['question' => 'The letter ___ written by John.', 'options' => ['is', 'was', 'were', 'are'], 'correct' => 1, 'level' => 'advanced', 'category' => 'passive_voice'],
            ['question' => 'The house ___ built in 1990.', 'options' => ['is', 'was', 'were', 'are'], 'correct' => 1, 'level' => 'advanced', 'category' => 'passive_voice'],
            ['question' => 'English ___ spoken in many countries.', 'options' => ['is', 'was', 'were', 'are'], 'correct' => 0, 'level' => 'advanced', 'category' => 'passive_voice'],

            // Reported Speech
            ['question' => 'She said she ___ tired.', 'options' => ['is', 'was', 'were', 'are'], 'correct' => 1, 'level' => 'advanced', 'category' => 'reported_speech'],
            ['question' => 'He told me he ___ finish the work tomorrow.', 'options' => ['will', 'would', 'shall', 'should'], 'correct' => 1, 'level' => 'advanced', 'category' => 'reported_speech'],
            ['question' => 'They said they ___ never been there before.', 'options' => ['have', 'has', 'had', 'having'], 'correct' => 2, 'level' => 'advanced', 'category' => 'reported_speech'],

            // Relative Clauses
            ['question' => 'The person ___ called you is my brother.', 'options' => ['who', 'which', 'what', 'where'], 'correct' => 0, 'level' => 'advanced', 'category' => 'relative_clauses'],
            ['question' => 'The book ___ I bought is very interesting.', 'options' => ['who', 'which', 'what', 'where'], 'correct' => 1, 'level' => 'advanced', 'category' => 'relative_clauses'],
            ['question' => 'This is the place ___ we first met.', 'options' => ['who', 'which', 'what', 'where'], 'correct' => 3, 'level' => 'advanced', 'category' => 'relative_clauses'],

            // Advanced Prepositions
            ['question' => 'She succeeded ___ passing the exam.', 'options' => ['in', 'on', 'at', 'to'], 'correct' => 0, 'level' => 'advanced', 'category' => 'prepositions'],
            ['question' => 'I am responsible ___ this project.', 'options' => ['for', 'of', 'with', 'to'], 'correct' => 0, 'level' => 'advanced', 'category' => 'prepositions'],
            ['question' => 'She apologized ___ being late.', 'options' => ['for', 'of', 'with', 'to'], 'correct' => 0, 'level' => 'advanced', 'category' => 'prepositions'],
        ];
    }

    private function getGrammarQuestionsByLevel($level)
    {
        // Este método aún se usa en Sentence Builder, mantener versión simplificada
        return [
            [
                'question' => 'She ___ to school every day.',
                'options' => ['go', 'goes', 'going', 'gone'],
                'correct' => 'goes',
            ],
            [
                'question' => 'They ___ playing soccer now.',
                'options' => ['is', 'am', 'are', 'be'],
                'correct' => 'are',
            ],
            [
                'question' => 'I ___ seen that movie before.',
                'options' => ['have', 'has', 'had', 'having'],
                'correct' => 'have',
            ],
        ];
    }

    /**
     * Obtener ejercicios de escucha por nivel
     */
    private function getListeningExercisesByLevel($level)
    {
        // Ejercicios básicos (Nivel 1-5) - 20 ejercicios disponibles, se seleccionan 5 aleatorios
        $basicAll = [
            [
                'text' => 'Hello, my name is Sarah. I am a teacher.',
                'question' => 'What is Sarah\'s job?',
                'options' => ['Teacher', 'Doctor', 'Engineer', 'Student'],
                'correct' => 'Teacher',
                'level' => 'basic',
            ],
            [
                'text' => 'I have two cats and one dog. They are my pets.',
                'question' => 'How many cats does the person have?',
                'options' => ['One', 'Two', 'Three', 'Four'],
                'correct' => 'Two',
                'level' => 'basic',
            ],
            [
                'text' => 'The school starts at eight o\'clock in the morning.',
                'question' => 'What time does school start?',
                'options' => ['Seven', 'Eight', 'Nine', 'Ten'],
                'correct' => 'Eight',
                'level' => 'basic',
            ],
            [
                'text' => 'My favorite color is blue. I like the sky.',
                'question' => 'What is their favorite color?',
                'options' => ['Red', 'Blue', 'Green', 'Yellow'],
                'correct' => 'Blue',
                'level' => 'basic',
            ],
            [
                'text' => 'We eat breakfast in the kitchen every morning.',
                'question' => 'Where do they eat breakfast?',
                'options' => ['Living room', 'Bedroom', 'Kitchen', 'Garden'],
                'correct' => 'Kitchen',
                'level' => 'basic',
            ],
            [
                'text' => 'My brother is tall. He plays basketball.',
                'question' => 'What sport does his brother play?',
                'options' => ['Football', 'Basketball', 'Tennis', 'Baseball'],
                'correct' => 'Basketball',
                'level' => 'basic',
            ],
            [
                'text' => 'She drinks coffee every morning with milk.',
                'question' => 'When does she drink coffee?',
                'options' => ['Morning', 'Afternoon', 'Evening', 'Night'],
                'correct' => 'Morning',
                'level' => 'basic',
            ],
            [
                'text' => 'There are seven days in a week.',
                'question' => 'How many days are in a week?',
                'options' => ['Five', 'Six', 'Seven', 'Eight'],
                'correct' => 'Seven',
                'level' => 'basic',
            ],
            [
                'text' => 'My car is red and very fast.',
                'question' => 'What color is the car?',
                'options' => ['Blue', 'Red', 'Green', 'Black'],
                'correct' => 'Red',
                'level' => 'basic',
            ],
            [
                'text' => 'I go to the gym three times a week.',
                'question' => 'How often do they go to the gym?',
                'options' => ['Once a week', 'Twice a week', 'Three times a week', 'Every day'],
                'correct' => 'Three times a week',
                'level' => 'basic',
            ],
            [
                'text' => 'The library opens at nine in the morning.',
                'question' => 'When does the library open?',
                'options' => ['Eight', 'Nine', 'Ten', 'Eleven'],
                'correct' => 'Nine',
                'level' => 'basic',
            ],
            [
                'text' => 'I like to read books before going to sleep.',
                'question' => 'When does the person read books?',
                'options' => ['In the morning', 'At lunch', 'Before sleep', 'At work'],
                'correct' => 'Before sleep',
                'level' => 'basic',
            ],
            [
                'text' => 'My sister has long black hair.',
                'question' => 'What color is her sister\'s hair?',
                'options' => ['Blonde', 'Red', 'Black', 'Brown'],
                'correct' => 'Black',
                'level' => 'basic',
            ],
            [
                'text' => 'We have lunch at twelve thirty every day.',
                'question' => 'What time do they have lunch?',
                'options' => ['Twelve', 'Twelve thirty', 'One', 'One thirty'],
                'correct' => 'Twelve thirty',
                'level' => 'basic',
            ],
            [
                'text' => 'The weather is sunny and warm today.',
                'question' => 'How is the weather today?',
                'options' => ['Rainy', 'Cold', 'Sunny', 'Snowy'],
                'correct' => 'Sunny',
                'level' => 'basic',
            ],
            [
                'text' => 'My father works in a hospital. He is a doctor.',
                'question' => 'Where does his father work?',
                'options' => ['School', 'Hospital', 'Office', 'Restaurant'],
                'correct' => 'Hospital',
                'level' => 'basic',
            ],
            [
                'text' => 'I usually walk to school because it is very close.',
                'question' => 'How does the person go to school?',
                'options' => ['By bus', 'By car', 'Walks', 'By bike'],
                'correct' => 'Walks',
                'level' => 'basic',
            ],
            [
                'text' => 'She has three children. Two boys and one girl.',
                'question' => 'How many children does she have?',
                'options' => ['Two', 'Three', 'Four', 'Five'],
                'correct' => 'Three',
                'level' => 'basic',
            ],
            [
                'text' => 'The supermarket is open until ten at night.',
                'question' => 'When does the supermarket close?',
                'options' => ['Eight', 'Nine', 'Ten', 'Eleven'],
                'correct' => 'Ten',
                'level' => 'basic',
            ],
            [
                'text' => 'My birthday is in December. I will be twenty years old.',
                'question' => 'When is the person\'s birthday?',
                'options' => ['November', 'December', 'January', 'February'],
                'correct' => 'December',
                'level' => 'basic',
            ],
        ];

        // Seleccionar 5 ejercicios aleatorios
        shuffle($basicAll);
        $basic = array_slice($basicAll, 0, 5);

        // Ejercicios intermedios (Nivel 6-15) - 15 ejercicios disponibles, se seleccionan 8 aleatorios
        $intermediateAll = [
            [
                'text' => 'Yesterday I went to the supermarket and bought some vegetables and fruits for dinner.',
                'question' => 'When did they go to the supermarket?',
                'options' => ['Today', 'Yesterday', 'Tomorrow', 'Last week'],
                'correct' => 'Yesterday',
                'level' => 'intermediate',
            ],
            [
                'text' => 'She has been studying English for five years and now she speaks fluently.',
                'question' => 'How long has she been studying English?',
                'options' => ['Three years', 'Four years', 'Five years', 'Six years'],
                'correct' => 'Five years',
                'level' => 'intermediate',
            ],
            [
                'text' => 'If it rains tomorrow, we will cancel the picnic and stay at home.',
                'question' => 'What will happen if it rains?',
                'options' => ['Go to the picnic', 'Cancel the picnic', 'Go to the beach', 'Visit friends'],
                'correct' => 'Cancel the picnic',
                'level' => 'intermediate',
            ],
            [
                'text' => 'The movie was so boring that I fell asleep halfway through.',
                'question' => 'What happened during the movie?',
                'options' => ['It was exciting', 'I fell asleep', 'I laughed a lot', 'I cried'],
                'correct' => 'I fell asleep',
                'level' => 'intermediate',
            ],
            [
                'text' => 'My sister is going to Paris next month to study fashion design.',
                'question' => 'What will she study in Paris?',
                'options' => ['Art', 'Fashion design', 'Architecture', 'Music'],
                'correct' => 'Fashion design',
                'level' => 'intermediate',
            ],
            [
                'text' => 'I would have helped you if I had known you were in trouble.',
                'question' => 'Why didn\'t they help?',
                'options' => ['They were busy', 'They didn\'t know', 'They didn\'t want to', 'They were sleeping'],
                'correct' => 'They didn\'t know',
                'level' => 'intermediate',
            ],
            [
                'text' => 'The restaurant where we had dinner last night had excellent service and delicious food.',
                'question' => 'How was the service at the restaurant?',
                'options' => ['Poor', 'Average', 'Good', 'Excellent'],
                'correct' => 'Excellent',
                'level' => 'intermediate',
            ],
            [
                'text' => 'Despite the rain, the outdoor concert continued and everyone enjoyed it.',
                'question' => 'What happened to the concert?',
                'options' => ['It was cancelled', 'It continued', 'It was postponed', 'It moved indoors'],
                'correct' => 'It continued',
                'level' => 'intermediate',
            ],
            [
                'text' => 'I have been working on this project for two weeks, but I still need more time to finish it.',
                'question' => 'Is the project finished?',
                'options' => ['Yes', 'No', 'Almost', 'Just started'],
                'correct' => 'No',
                'level' => 'intermediate',
            ],
            [
                'text' => 'She suggested that we meet at the coffee shop on the corner at three o\'clock.',
                'question' => 'Where did she suggest meeting?',
                'options' => ['At the park', 'At her house', 'At the coffee shop', 'At the library'],
                'correct' => 'At the coffee shop',
                'level' => 'intermediate',
            ],
            [
                'text' => 'After finishing university, he decided to travel around the world for a year.',
                'question' => 'What did he do after university?',
                'options' => ['Started working', 'Traveled', 'Got married', 'Studied more'],
                'correct' => 'Traveled',
                'level' => 'intermediate',
            ],
            [
                'text' => 'We were watching television when suddenly the lights went out.',
                'question' => 'What were they doing when the lights went out?',
                'options' => ['Eating', 'Sleeping', 'Watching TV', 'Reading'],
                'correct' => 'Watching TV',
                'level' => 'intermediate',
            ],
            [
                'text' => 'She used to live in New York, but now she lives in Los Angeles.',
                'question' => 'Where does she live now?',
                'options' => ['New York', 'Los Angeles', 'Chicago', 'Boston'],
                'correct' => 'Los Angeles',
                'level' => 'intermediate',
            ],
            [
                'text' => 'By the time we arrived at the station, the train had already left.',
                'question' => 'What happened when they arrived?',
                'options' => ['Train was waiting', 'Train had left', 'Train was late', 'Train was cancelled'],
                'correct' => 'Train had left',
                'level' => 'intermediate',
            ],
            [
                'text' => 'I wish I could speak French fluently like my sister does.',
                'question' => 'Can the person speak French fluently?',
                'options' => ['Yes', 'No', 'A little', 'Better than sister'],
                'correct' => 'No',
                'level' => 'intermediate',
            ],
        ];

        // Seleccionar 8 ejercicios aleatorios
        shuffle($intermediateAll);
        $intermediate = array_slice($intermediateAll, 0, 8);

        // Ejercicios avanzados (Nivel 16+) - 15 ejercicios disponibles, se seleccionan 10 aleatorios
        $advancedAll = [
            [
                'text' => 'The company\'s revenue has increased significantly over the past quarter due to innovative marketing strategies.',
                'question' => 'Why did revenue increase?',
                'options' => ['Lower prices', 'Marketing strategies', 'More employees', 'New location'],
                'correct' => 'Marketing strategies',
                'level' => 'advanced',
            ],
            [
                'text' => 'Had I known about the traffic jam, I would have taken an alternative route to avoid being late.',
                'question' => 'What is the speaker\'s regret?',
                'options' => ['Not knowing about traffic', 'Being late', 'Missing a meeting', 'Leaving early'],
                'correct' => 'Not knowing about traffic',
                'level' => 'advanced',
            ],
            [
                'text' => 'The research indicates that regular exercise not only improves physical health but also enhances mental well-being.',
                'question' => 'According to the research, exercise affects what?',
                'options' => ['Only physical health', 'Only mental health', 'Both physical and mental health', 'Neither'],
                'correct' => 'Both physical and mental health',
                'level' => 'advanced',
            ],
            [
                'text' => 'The controversial decision made by the committee sparked heated debates among stakeholders.',
                'question' => 'What did the committee\'s decision cause?',
                'options' => ['Agreement', 'Celebration', 'Debates', 'Silence'],
                'correct' => 'Debates',
                'level' => 'advanced',
            ],
            [
                'text' => 'She is considering whether to pursue a master\'s degree or to gain practical experience in the field.',
                'question' => 'What is she deciding between?',
                'options' => ['Two jobs', 'Study or work', 'Two cities', 'Two subjects'],
                'correct' => 'Study or work',
                'level' => 'advanced',
            ],
            [
                'text' => 'The novel explores themes of identity and belonging through the protagonist\'s journey across continents.',
                'question' => 'What does the novel explore?',
                'options' => ['History', 'Identity and belonging', 'Science', 'Politics'],
                'correct' => 'Identity and belonging',
                'level' => 'advanced',
            ],
            [
                'text' => 'Notwithstanding the challenges, the team managed to complete the project ahead of schedule.',
                'question' => 'When was the project completed?',
                'options' => ['Late', 'On time', 'Ahead of schedule', 'It wasn\'t completed'],
                'correct' => 'Ahead of schedule',
                'level' => 'advanced',
            ],
            [
                'text' => 'The implementation of sustainable practices has become imperative for businesses to remain competitive.',
                'question' => 'Why are sustainable practices important?',
                'options' => ['For decoration', 'To remain competitive', 'For fun', 'They aren\'t important'],
                'correct' => 'To remain competitive',
                'level' => 'advanced',
            ],
            [
                'text' => 'It is essential that the findings be verified through independent research before publication.',
                'question' => 'What must happen before publication?',
                'options' => ['Nothing', 'Verification', 'Celebration', 'Translation'],
                'correct' => 'Verification',
                'level' => 'advanced',
            ],
            [
                'text' => 'The symposium addressed pressing issues related to climate change and proposed actionable solutions.',
                'question' => 'What was discussed at the symposium?',
                'options' => ['Sports', 'Climate change', 'Entertainment', 'Fashion'],
                'correct' => 'Climate change',
                'level' => 'advanced',
            ],
            [
                'text' => 'The proliferation of digital technologies has fundamentally transformed the way we communicate and interact.',
                'question' => 'What has transformed communication?',
                'options' => ['Traditional methods', 'Digital technologies', 'Government policies', 'Social norms'],
                'correct' => 'Digital technologies',
                'level' => 'advanced',
            ],
            [
                'text' => 'Contrary to popular belief, the author argued that economic growth does not necessarily correlate with happiness.',
                'question' => 'What is the author\'s argument?',
                'options' => ['Growth equals happiness', 'Growth doesn\'t equal happiness', 'Both are unrelated', 'Both decrease together'],
                'correct' => 'Growth doesn\'t equal happiness',
                'level' => 'advanced',
            ],
            [
                'text' => 'The legislation aims to mitigate the adverse effects of pollution while fostering economic development.',
                'question' => 'What is the legislation\'s goal?',
                'options' => ['Only reduce pollution', 'Only economic growth', 'Both reduce pollution and grow economy', 'Neither'],
                'correct' => 'Both reduce pollution and grow economy',
                'level' => 'advanced',
            ],
            [
                'text' => 'Scholars have debated whether artificial intelligence will augment human capabilities or render certain professions obsolete.',
                'question' => 'What do scholars debate about AI?',
                'options' => ['Its cost', 'Its effect on jobs', 'Its color', 'Its size'],
                'correct' => 'Its effect on jobs',
                'level' => 'advanced',
            ],
            [
                'text' => 'The perpetuation of stereotypes can undermine efforts to achieve genuine equality in contemporary society.',
                'question' => 'What can undermine equality efforts?',
                'options' => ['Education', 'Stereotypes', 'Technology', 'Laws'],
                'correct' => 'Stereotypes',
                'level' => 'advanced',
            ],
        ];

        // Seleccionar 10 ejercicios aleatorios
        shuffle($advancedAll);
        $advanced = array_slice($advancedAll, 0, 10);

        // Filtrar por nivel
        if ($level <= 5) {
            return $basic;
        } elseif ($level <= 15) {
            return array_merge($basic, $intermediate);
        } else {
            return array_merge($basic, $intermediate, $advanced);
        }
    }

    /**
     * Calcular XP ganado
     */
    private function calculateXP($data)
    {
        $baseXP = 10;
        $accuracyBonus = ($data['accuracy'] / 100) * 20;
        $speedBonus = max(0, 10 - ($data['time_spent'] / 60));

        return (int) ($baseXP + $accuracyBonus + $speedBonus) * $data['level'];
    }

    /**
     * Verificar logros
     */
    private function checkAchievements($user, $data)
    {
        // Verificar si hay logros nuevos
        $totalSessions = DB::table('english_game_sessions')
            ->where('user_id', $user->id)
            ->where('game_type', $data['game_type'])
            ->count();

        // Logros por cantidad de sesiones
        if ($totalSessions == 10) {
            // Otorgar logro "English Enthusiast"
        } elseif ($totalSessions == 50) {
            // Otorgar logro "English Master"
        }

        // Logros por precisión
        if ($data['accuracy'] >= 95) {
            // Otorgar logro "Perfect Score"
        }
    }

    /**
     * Determinar calidad de la sesión para engagement
     */
    private function determineActivityQualityEnum($accuracy, $timeSpent)
    {
        // Basado principalmente en duración para coincidir con enum del modelo
        if ($timeSpent < 300) { // < 5 minutos
            return 'micro';
        }
        if ($timeSpent < 900) { // 5-15 minutos
            return 'productive';
        }

        return 'excellent'; // > 15 minutos
    }
}
