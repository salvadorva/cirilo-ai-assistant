<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticAudio;
use App\Services\AudioCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StaticAudioController extends Controller
{
    protected $audioCacheService;

    public function __construct(AudioCacheService $audioCacheService)
    {
        $this->audioCacheService = $audioCacheService;
    }

    /**
     * Lista todos los audios estáticos
     */
    public function index(Request $request)
    {
        $query = StaticAudio::query();

        // Filtrar por tipo
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filtrar por estado de aprobación
        if ($request->has('approved')) {
            $query->where('is_approved', $request->boolean('approved'));
        }

        // Filtrar por estado activo
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $audios = $query->orderBy('type')->orderBy('index')->paginate(20);

        // Estadísticas
        $stats = [
            'total' => StaticAudio::count(),
            'approved' => StaticAudio::where('is_approved', true)->count(),
            'active' => StaticAudio::where('is_active', true)->count(),
            'pending' => StaticAudio::where('is_approved', false)->count(),
        ];

        return view('admin.static-audios.index', compact('audios', 'stats'));
    }

    /**
     * Muestra el detalle de un audio específico
     */
    public function show($id)
    {
        $audio = StaticAudio::findOrFail($id);

        return view('admin.static-audios.edit', compact('audio'));
    }

    /**
     * Regenera el audio con el mismo texto
     */
    public function regenerate($id)
    {
        try {
            $audio = StaticAudio::findOrFail($id);

            $newAudio = $this->audioCacheService->regenerateAudio($audio);

            if ($newAudio) {
                return response()->json([
                    'success' => true,
                    'message' => 'Audio regenerado exitosamente',
                    'audio_url' => $newAudio->audio_url,
                    'regeneration_count' => $newAudio->regeneration_count,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al regenerar el audio',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error al regenerar audio: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al regenerar el audio: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el texto y regenera el audio
     */
    public function updateText(Request $request, $id)
    {
        $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        try {
            $audio = StaticAudio::findOrFail($id);

            // Actualizar el texto
            $audio->text = $request->text;
            $audio->save();

            // Regenerar el audio con el nuevo texto
            $newAudio = $this->audioCacheService->regenerateAudio($audio);

            if ($newAudio) {
                return redirect()
                    ->route('admin.audios.show', $audio->id)
                    ->with('success', 'Texto actualizado y audio regenerado exitosamente');
            }

            return redirect()
                ->route('admin.audios.show', $audio->id)
                ->with('error', 'Error al regenerar el audio');

        } catch (\Exception $e) {
            Log::error('Error al actualizar texto: '.$e->getMessage());

            return redirect()
                ->route('admin.audios.show', $id)
                ->with('error', 'Error al actualizar el texto: '.$e->getMessage());
        }
    }

    /**
     * Aprueba y activa un audio
     */
    public function approve($id)
    {
        try {
            $audio = StaticAudio::findOrFail($id);

            // Si es un audio de bienvenida o frase graciosa, desactivar otros del mismo tipo/índice
            if ($audio->type === 'welcome') {
                StaticAudio::where('type', 'welcome')
                    ->where('id', '!=', $audio->id)
                    ->update(['is_active' => false]);
            } elseif ($audio->type === 'funny_phrase' && $audio->index !== null) {
                StaticAudio::where('type', 'funny_phrase')
                    ->where('index', $audio->index)
                    ->where('id', '!=', $audio->id)
                    ->update(['is_active' => false]);
            }

            $audio->is_approved = true;
            $audio->is_active = true;
            $audio->save();

            return response()->json([
                'success' => true,
                'message' => 'Audio aprobado y activado exitosamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al aprobar audio: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el audio: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alterna el estado activo de un audio
     */
    public function toggleActive($id)
    {
        try {
            $audio = StaticAudio::findOrFail($id);
            $audio->is_active = ! $audio->is_active;
            $audio->save();

            return response()->json([
                'success' => true,
                'is_active' => $audio->is_active,
                'message' => $audio->is_active ? 'Audio activado' : 'Audio desactivado',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al cambiar estado activo: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Agrega una nueva frase graciosa
     */
    public function addFunnyPhrase(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        try {
            // Obtener el próximo índice disponible
            $maxIndex = StaticAudio::where('type', 'funny_phrase')->max('index') ?? -1;
            $nextIndex = $maxIndex + 1;

            // Generar el audio
            $audio = $this->audioCacheService->generateStaticAudio(
                'funny_phrase',
                $request->text,
                $nextIndex
            );

            if ($audio) {
                return response()->json([
                    'success' => true,
                    'message' => 'Frase graciosa agregada exitosamente',
                    'audio' => $audio,
                    'audio_url' => $audio->audio_url,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el audio',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Error al agregar frase graciosa: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al agregar la frase: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina un audio (solo si no está aprobado)
     */
    public function delete($id)
    {
        try {
            $audio = StaticAudio::findOrFail($id);

            if ($audio->is_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un audio aprobado',
                ], 403);
            }

            // Eliminar el archivo físico
            if ($audio->file_path && Storage::disk('public')->exists($audio->file_path)) {
                Storage::disk('public')->delete($audio->file_path);
            }

            $audio->delete();

            return response()->json([
                'success' => true,
                'message' => 'Audio eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar audio: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el audio: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el audio de bienvenida para uso público
     */
    public function getWelcomeAudio()
    {
        $welcomeText = 'Hola, me llamo Cirílo! Estoy aquí para ayudarte con todo lo que necesites. Puedes hacerme preguntas en el Asistente Virtual, pedirme que genere imágenes creativas, analizar imágenes que subas, usar el modo creativo para inspirarte o revisar tu historial de conversaciones. ¡También puedes hablarme usando el micrófono!';

        $audio = $this->audioCacheService->getCachedAudio('welcome', null, $welcomeText);

        if ($audio && $audio->audioFileExists()) {
            return response()->json([
                'success' => true,
                'audio_url' => $audio->audio_url,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Audio de bienvenida no disponible',
        ], 404);
    }

    /**
     * Obtiene una frase graciosa específica para uso público
     */
    public function getFunnyPhrase($index)
    {
        $audio = StaticAudio::where('type', 'funny_phrase')
            ->where('index', $index)
            ->active()
            ->first();

        if ($audio && $audio->audioFileExists()) {
            return response()->json([
                'success' => true,
                'audio_url' => $audio->audio_url,
                'text' => $audio->text,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Frase graciosa no disponible',
        ], 404);
    }
}
