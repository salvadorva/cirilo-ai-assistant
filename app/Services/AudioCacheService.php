<?php

namespace App\Services;

use App\Models\StaticAudio;
use Illuminate\Support\Facades\Http;
use App\Support\AiLog as Log;
use Illuminate\Support\Facades\Storage;

class AudioCacheService
{
    /**
     * Genera un audio estático usando la API de OpenAI TTS
     *
     * @param  string  $type  Tipo de audio ('welcome' o 'funny_phrase')
     * @param  string  $text  Texto a convertir a voz
     * @param  int|null  $index  Índice para frases graciosas
     * @return StaticAudio|null
     */
    public function generateStaticAudio($type, $text, $index = null)
    {
        try {
            Log::info('Generando audio estático', [
                'type' => $type,
                'text' => substr($text, 0, 50).'...',
                'index' => $index,
            ]);

            // Obtener credenciales de OpenAI
            $apiKey = config('services.openai.api_key', '');

            // Realizar solicitud a la API de OpenAI para text-to-speech
            $response = Http::timeout(120)->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/audio/speech', [
                'model' => config('ai.models.tts'),
                'input' => $text,
                'voice' => 'echo', // Voz específica solicitada
                'output_format' => 'mp3',
            ]);

            if (! $response->successful()) {
                Log::error('Error al generar audio estático', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            // Crear directorio si no existe
            if (! Storage::disk('public')->exists('audio/static')) {
                Storage::disk('public')->makeDirectory('audio/static');
            }

            // Generar nombre de archivo
            $filename = $this->generateFilename($type, $index);
            $filePath = 'audio/static/'.$filename;

            // Guardar el archivo
            Storage::disk('public')->put($filePath, $response->body());

            // Buscar o crear el registro en la base de datos
            $staticAudio = StaticAudio::updateOrCreate(
                [
                    'type' => $type,
                    'index' => $index,
                ],
                [
                    'text' => $text,
                    'file_path' => $filePath,
                    'regeneration_count' => \DB::raw('regeneration_count + 1'),
                    'last_regenerated_at' => now(),
                ]
            );

            Log::info('Audio estático generado exitosamente', [
                'id' => $staticAudio->id,
                'file_path' => $filePath,
            ]);

            return $staticAudio;

        } catch (\Exception $e) {
            Log::error('Excepción al generar audio estático: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Obtiene un audio estático en caché o lo genera si no existe
     *
     * @param  string  $type  Tipo de audio ('welcome' o 'funny_phrase')
     * @param  int|null  $index  Índice para frases graciosas
     * @param  string|null  $text  Texto (solo necesario si se va a generar)
     * @return StaticAudio|null
     */
    public function getCachedAudio($type, $index = null, $text = null)
    {
        // Intentar obtener el audio aprobado y activo
        $audio = StaticAudio::where('type', $type)
            ->where('index', $index)
            ->approved()
            ->active()
            ->first();

        // Si existe y el archivo físico está presente, retornarlo
        if ($audio && $audio->audioFileExists()) {
            return $audio;
        }

        // Si no existe y tenemos texto, generarlo
        if ($text) {
            return $this->generateStaticAudio($type, $text, $index);
        }

        // Intentar obtener cualquier audio existente (aunque no esté aprobado)
        $audio = StaticAudio::where('type', $type)
            ->where('index', $index)
            ->first();

        if ($audio && $audio->audioFileExists()) {
            return $audio;
        }

        return null;
    }

    /**
     * Limpia audios dinámicos antiguos
     *
     * @param  int  $days  Número de días a conservar
     * @return array Estadísticas de limpieza
     */
    public function cleanDynamicAudios($days = 7)
    {
        $stats = [
            'total_checked' => 0,
            'deleted' => 0,
            'failed' => 0,
            'freed_space' => 0,
        ];

        try {
            $cutoffDate = now()->subDays($days);
            $directory = 'audio/dynamic';

            if (! Storage::disk('public')->exists($directory)) {
                Log::info('Directorio audio/dynamic no existe');

                return $stats;
            }

            $files = Storage::disk('public')->files($directory);
            $stats['total_checked'] = count($files);

            foreach ($files as $file) {
                $lastModified = Storage::disk('public')->lastModified($file);
                $fileDate = \Carbon\Carbon::createFromTimestamp($lastModified);

                if ($fileDate->lt($cutoffDate)) {
                    $size = Storage::disk('public')->size($file);

                    if (Storage::disk('public')->delete($file)) {
                        $stats['deleted']++;
                        $stats['freed_space'] += $size;
                    } else {
                        $stats['failed']++;
                    }
                }
            }

            Log::info('Limpieza de audios dinámicos completada', $stats);

            return $stats;

        } catch (\Exception $e) {
            Log::error('Error al limpiar audios dinámicos: '.$e->getMessage());

            return $stats;
        }
    }

    /**
     * Genera un nombre de archivo único basado en el tipo e índice
     *
     * @param  string  $type
     * @param  int|null  $index
     * @return string
     */
    private function generateFilename($type, $index = null)
    {
        if ($type === 'welcome') {
            return 'welcome_message.mp3';
        }

        if ($type === 'funny_phrase' && $index !== null) {
            return "funny_phrase_{$index}.mp3";
        }

        // Fallback
        return $type.'_'.uniqid().'.mp3';
    }

    /**
     * Regenera un audio existente
     *
     * @return StaticAudio|null
     */
    public function regenerateAudio(StaticAudio $audio)
    {
        // Eliminar el archivo antiguo si existe
        if ($audio->file_path && Storage::disk('public')->exists($audio->file_path)) {
            Storage::disk('public')->delete($audio->file_path);
        }

        // Generar nuevo audio
        return $this->generateStaticAudio($audio->type, $audio->text, $audio->index);
    }
}
