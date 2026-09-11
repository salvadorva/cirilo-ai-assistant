<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreativeModeController extends Controller
{
    /**
     * Mostrar la vista del modo creativo.
     */
    public function index()
    {
        $user = Auth::user();
        $role = $user && $user->role ? $user->role->name : null;

        return view('creative.index', compact('user', 'role'));
    }

    /**
     * Generar una idea creativa.
     */
    public function generateIdea(Request $request)
    {
        $user = Auth::user();
        $provider = $user->ai_provider ?? 'openai';

        // Lista de prompts creativos para generar ideas aleatorias
        $creativePrompts = [
            'Genera una idea innovadora para solucionar un problema cotidiano.',
            'Inventa una historia corta inspiradora con un giro inesperado.',
            'Propón un concepto futurista que podría ser realidad en 10 años.',
            'Crea una reflexión filosófica sobre la naturaleza humana.',
            'Sugiere una actividad creativa que estimule la imaginación.',
            'Inventa un concepto para una obra de arte conceptual.',
            'Crea una metáfora poética sobre la vida.',
            'Propón un experimento mental fascinante.',
            'Genera una pregunta profunda que haga reflexionar.',
            'Inventa un concepto para un invento revolucionario.',
        ];

        // Seleccionar un prompt aleatorio
        $selectedPrompt = $creativePrompts[array_rand($creativePrompts)];

        // Si el usuario envió un tema específico, incorporarlo al prompt
        $theme = $request->input('theme');
        if ($theme) {
            $selectedPrompt .= " El tema debe estar relacionado con: $theme.";
        }

        try {
            // Obtener credenciales de API según el proveedor configurado
            $credentials = $this->getApiCredentials($provider);

            // Preparar el prompt personalizado si existe
            $userPrompt = $user && $user->prompt ? $user->prompt : '';
            $finalPrompt = $userPrompt
                ? "$userPrompt\n\nActúa en modo creativo. $selectedPrompt"
                : "Actúa en modo creativo. $selectedPrompt";

            // Llamada a la API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->post($credentials['base_url'].'/chat/completions', [
                'model' => $provider === 'openai' ? 'gpt-4' : 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un asistente creativo que genera ideas inspiradoras, reflexiones profundas y conceptos innovadores.'],
                    ['role' => 'user', 'content' => $finalPrompt],
                ],
                'temperature' => 0.9,
                'max_tokens' => 500,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $content = $responseData['choices'][0]['message']['content'] ?? '';

                return response()->json([
                    'success' => true,
                    'content' => $content,
                    'prompt' => $selectedPrompt,
                ]);
            } else {
                Log::error('Error en la respuesta de '.$provider.': '.$response->body());

                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar la idea creativa.',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Excepción en modo creativo: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar una conversación creativa.
     */
    public function saveCreativeConversation(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|json',
        ]);

        $conversation = new Conversation;
        $conversation->user_id = Auth::id();
        $conversation->title = $request->title;
        $conversation->type = 'creative';
        $conversation->content = $request->content;
        $conversation->save();

        return response()->json([
            'success' => true,
            'message' => 'Conversación creativa guardada correctamente',
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Obtener credenciales de API según el proveedor.
     */
    private function getApiCredentials($provider)
    {
        switch ($provider) {
            case 'openai':
                return [
                    'api_key' => config('services.openai.api_key'),
                    'base_url' => config('services.openai.base_url', 'https://api.openai.com/v1'),
                ];
            case 'grok':
                return [
                    'api_key' => config('services.grok.api_key'),
                    'base_url' => config('services.grok.base_url', 'https://api.x.ai/v1'),
                ];
            default:
                // Por defecto usar OpenAI
                return [
                    'api_key' => config('services.openai.api_key'),
                    'base_url' => config('services.openai.base_url', 'https://api.openai.com/v1'),
                ];
        }
    }

    /**
     * Generar contenido creativo basado en un prompt.
     */
    public function generate(Request $request)
    {
        $user = Auth::user();
        $provider = $user->ai_provider ?? 'openai';

        $prompt = $request->input('prompt');
        $detailed = $request->input('detailed', false);
        $creativity = $request->input('creativity', 'balanced');

        if (empty($prompt)) {
            return response()->json([
                'success' => false,
                'message' => 'El prompt no puede estar vacío',
            ], 400);
        }

        try {
            // Obtener credenciales de API según el proveedor configurado
            $credentials = $this->getApiCredentials($provider);

            // Ajustar temperatura según el nivel de creatividad
            $temperature = 0.7; // Valor por defecto (balanced)
            if ($creativity === 'creative') {
                $temperature = 0.9;
            } elseif ($creativity === 'precise') {
                $temperature = 0.5;
            }

            // Preparar el sistema prompt según el modo detallado
            $systemPrompt = $detailed
                ? 'Eres un asistente creativo que genera respuestas detalladas y elaboradas. Profundiza en los temas y proporciona ejemplos concretos.'
                : 'Eres un asistente creativo que genera respuestas concisas y directas.';

            // Determinar el modelo adecuado según el proveedor
            $model = 'gpt-4o';
            if ($provider === 'grok') {
                $model = 'grok-3';
            }

            // Llamada a la API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->post($credentials['base_url'].'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $temperature,
                'max_tokens' => $detailed ? 1000 : 500,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Respuesta de la API: '.json_encode($responseData));
                $content = '';

                // Extraer el contenido según la estructura de respuesta
                if (isset($responseData['choices'][0]['message']['content'])) {
                    $content = $responseData['choices'][0]['message']['content'];
                }

                return response()->json([
                    'success' => true,
                    'content' => $content,
                ]);
            } else {
                Log::error('Error en la respuesta de '.$provider.': '.$response->body());

                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar el contenido creativo: '.$response->body(),
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Excepción en modo creativo: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar una creación.
     */
    public function save(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'content' => 'required|json',
        ]);

        $conversation = new Conversation;
        $conversation->user_id = Auth::id();
        $conversation->title = $request->title;
        $conversation->type = 'creative';
        $conversation->content = $request->content;
        $conversation->save();

        return response()->json([
            'success' => true,
            'message' => 'Creación guardada correctamente',
            'conversation_id' => $conversation->id,
        ]);
    }
}
