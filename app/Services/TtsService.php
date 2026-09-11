<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Genera audio TTS con OpenAI de forma independiente del request HTTP,
 * usable desde comandos de consola (el textToSpeech de AIController
 * depende de auth()->user() y Request).
 *
 * Por defecto guarda los mp3 en audio/dynamic/ igual que el TTS del chat,
 * así el comando audio:clean los limpia con la misma política. Con $savePath
 * se puede fijar una ruta fuera de esa carpeta (p. ej. audio/focus/) para
 * audios cacheados que deben persistir hasta que su dueño los invalide.
 */
class TtsService
{
    public const ALLOWED_VOICES = ['alloy', 'echo', 'fable', 'nova', 'onyx', 'shimmer'];

    private const MAX_LENGTH = 3000;

    /**
     * Genera un mp3 y devuelve su URL pública, o null si algo falla.
     */
    public function generateMp3(string $text, string $voice = 'echo', ?string $savePath = null): ?string
    {
        if (! in_array($voice, self::ALLOWED_VOICES, true)) {
            $voice = 'echo';
        }

        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (strlen($text) > self::MAX_LENGTH) {
            $cutPoint = strrpos(substr($text, 0, self::MAX_LENGTH), '.')
                ?: strrpos(substr($text, 0, self::MAX_LENGTH), ' ')
                ?: self::MAX_LENGTH;
            $text = substr($text, 0, $cutPoint + 1);
        }

        try {
            $response = Http::timeout(120)
                ->retry(3, 100)
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.openai.api_key'),
                    'Content-Type' => 'application/json',
                ])->post('https://api.openai.com/v1/audio/speech', [
                    'model' => 'tts-1',
                    'input' => mb_convert_encoding($text, 'UTF-8', 'auto'),
                    'voice' => $voice,
                    'output_format' => 'mp3',
                ]);

            if ($response->failed()) {
                Log::error('[TTS] Error de OpenAI TTS: '.$response->status().' '.$response->body());

                return null;
            }

            $fileName = $savePath ?? 'audio/dynamic/'.uniqid().'.mp3';

            $directory = dirname($fileName);
            if (! Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            Storage::disk('public')->put($fileName, $response->body());

            return Storage::disk('public')->url($fileName);
        } catch (\Throwable $e) {
            Log::error('[TTS] Excepción generando audio: '.$e->getMessage());

            return null;
        }
    }
}
