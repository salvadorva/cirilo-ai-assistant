<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\AIController;
use App\Http\Controllers\Controller;
use App\Models\ApiUsageLog;
use App\Traits\LogsApiUsage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MobileImageController extends Controller
{
    use LogsApiUsage;

    /**
     * POST /api/mobile/images/generate
     * Delega a AIController::generateImage. El middleware image.limit ya
     * controla la cuota diaria por usuario.
     */
    public function generate(Request $request, AIController $aiController): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
        ]);

        $response = $aiController->generateImage($request);
        $data = $response->getData(true);

        // Normalizar para móvil
        $imageUrl = $data['url']
            ?? $data['image_url']
            ?? $data['data'][0]['url']
            ?? null;

        return response()->json([
            'url'             => $imageUrl,
            'prompt_used'     => $data['revised_prompt'] ?? $request->input('prompt'),
            'remaining_today' => $this->remainingToday($request->user()),
            'raw'             => $data,
        ], $response->getStatusCode());
    }

    /**
     * POST /api/mobile/images/analyze
     * Multipart: image (file) + prompt (opcional).
     */
    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'image'  => 'required|image|max:5120', // 5 MB
            'prompt' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        try {
            $imagePath = $request->file('image')->store('temp_images', 'public');
            $fullPath = Storage::disk('public')->path($imagePath);
            $imageData = base64_encode(file_get_contents($fullPath));

            $prompt = $request->input('prompt')
                ?: '¿Qué hay en esta imagen? Por favor, describe detalladamente lo que ves.';

            $userPrompt = $user->prompt ?? '';
            if ($userPrompt) {
                $prompt = "$userPrompt\n\nAnaliza esta imagen: $prompt";
            }

            $startTime = microtime(true);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.openai.api_key'),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => "data:image/jpeg;base64,{$imageData}",
                        ]],
                    ],
                ]],
                'max_tokens' => 500,
            ]);

            // Mover a ubicación permanente
            $permanentPath = 'images/analysis/'.time().'_'.basename($imagePath);
            Storage::disk('public')->move($imagePath, $permanentPath);

            if (! $response->successful()) {
                Log::error('[MobileImage] Vision API falló: '.$response->body());

                return response()->json([
                    'message' => 'Error al analizar la imagen.',
                ], 500);
            }

            $data = $response->json();
            $analysis = $data['choices'][0]['message']['content'] ?? '';
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            $this->logImageAnalysis($prompt, $data, $responseTime);

            return response()->json([
                'analysis'   => $analysis,
                'image_url'  => Storage::disk('public')->url($permanentPath),
                'model'      => 'gpt-4o',
                'tokens'     => $data['usage'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('[MobileImage] Excepción: '.$e->getMessage());

            return response()->json([
                'message' => 'Ocurrió un error procesando la imagen.',
            ], 500);
        }
    }

    private function remainingToday($user): ?int
    {
        if ($user->role && $user->role->name === 'admin') {
            return null; // sin límite
        }

        $limit = $user->daily_image_limit ?? 4;
        if ($limit === 0) {
            return null;
        }

        $count = ApiUsageLog::where('user_id', $user->id)
            ->where('api_type', 'image_generation')
            ->where('status', 'success')
            ->whereDate('created_at', Carbon::today())
            ->count();

        return max(0, $limit - $count);
    }
}
