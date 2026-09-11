<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Traits\LogsApiUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageAnalysisController extends Controller
{
    use LogsApiUsage;

    /**
     * Mostrar la vista de análisis de imágenes.
     */
    public function index()
    {
        $user = Auth::user();
        $role = $user && $user->role ? $user->role->name : null;

        return view('image_analysis.index', compact('user', 'role'));
    }

    /**
     * Analizar una imagen utilizando GPT-4 Vision.
     */
    public function analyzeImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:4096', // Max 4MB
            'prompt' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();

        try {
            // Guardar la imagen temporalmente
            $imagePath = $request->file('image')->store('temp_images', 'public');
            $fullImagePath = Storage::disk('public')->path($imagePath);
            $imageData = base64_encode(file_get_contents($fullImagePath));

            // Preparar el prompt para el análisis
            $prompt = $request->input('prompt') ?? '¿Qué hay en esta imagen? Por favor, describe detalladamente lo que ves.';

            // Personalizar el prompt si el usuario tiene un prompt personalizado
            $userPrompt = $user && $user->prompt ? $user->prompt : '';
            if ($userPrompt) {
                $prompt = "$userPrompt\n\nAnaliza esta imagen: $prompt";
            }

            // Llamar a la API de OpenAI (GPT-4 Vision)
            $credentials = $this->getApiCredentials();
            $startTime = microtime(true);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$credentials['api_key'],
                'Content-Type' => 'application/json',
            ])->post($credentials['base_url'].'/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:image/jpeg;base64,{$imageData}",
                                ],
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 500,
            ]);

            // Mover la imagen de temp a una ubicación permanente
            $permanentPath = 'images/analysis/'.time().'_'.basename($imagePath);
            Storage::disk('public')->move($imagePath, $permanentPath);

            // Eliminar el archivo temporal
            Storage::disk('public')->delete($imagePath);

            if ($response->successful()) {
                $responseTime = (int) ((microtime(true) - $startTime) * 1000);
                $responseData = $response->json();
                $content = $responseData['choices'][0]['message']['content'] ?? '';

                // Registrar uso exitoso
                $this->logImageAnalysis($prompt, $responseData, $responseTime);

                return response()->json([
                    'success' => true,
                    'content' => $content,
                    'image_path' => Storage::disk('public')->url($permanentPath),
                ]);
            } else {
                Log::error('Error en la respuesta de OpenAI Vision: '.$response->body());

                return response()->json([
                    'success' => false,
                    'message' => 'Error al analizar la imagen.',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Excepción en análisis de imagen: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene las credenciales de la API de OpenAI
     *
     * @return array Credenciales de la API
     */
    private function getApiCredentials()
    {
        return [
            'api_key' => config('services.openai.api_key', ''),
            'base_url' => 'https://api.openai.com/v1',
        ];
    }

    /**
     * Guardar una conversación de análisis de imagen.
     */
    public function saveImageAnalysis(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|json',
            'image_path' => 'required|string',
        ]);

        $conversation = new Conversation;
        $conversation->user_id = Auth::id();
        $conversation->title = $request->title;
        $conversation->type = 'image_analysis';
        $conversation->content = $request->content;
        $conversation->save();

        // Guardar el mensaje con la imagen
        $message = new Message;
        $message->conversation_id = $conversation->id;
        $message->role = 'user';
        $message->content = 'Analiza esta imagen';
        $message->image_path = $request->image_path;
        $message->save();

        // Guardar la respuesta del asistente
        $content = json_decode($request->content, true);
        if (isset($content['assistant'])) {
            $message = new Message;
            $message->conversation_id = $conversation->id;
            $message->role = 'assistant';
            $message->content = $content['assistant'];
            $message->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Análisis de imagen guardado correctamente',
            'conversation_id' => $conversation->id,
        ]);
    }
}
