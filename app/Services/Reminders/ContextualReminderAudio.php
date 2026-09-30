<?php

namespace App\Services\Reminders;

use App\Models\ContextualReminder;
use App\Services\AiTelemetry;
use App\Services\TtsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Audio opcional y privado de un recordatorio contextual (RC4). Se genera en
 * un job aparte con la plantilla fija del texto acordado; si falla o no está
 * listo, el recordatorio funciona igual como texto. Solo con audio_enabled:
 * el piloto aprobado es sin audio.
 */
class ContextualReminderAudio
{
    public const DISK = 'local';

    public function __construct(private TtsService $tts) {}

    /** Plantilla determinista: solo lee lo acordado, no genera consejos. */
    public static function text(ContextualReminder $reminder): string
    {
        $sentence = fn (string $text) => rtrim(trim($text), '.').'.';

        return 'Recordatorio acordado: '.$sentence($reminder->title).' Siguiente acción: '.$sentence($reminder->next_action);
    }

    public function generate(string $reminderId): void
    {
        $reminder = ContextualReminder::find($reminderId);
        if (! config('reminders.audio_enabled') || ! $reminder || ! $reminder->with_audio || ! $this->isOpen($reminder)) {
            return;
        }
        $path = $this->pathFor($reminder);
        if ($reminder->audio_path === $path && Storage::disk(self::DISK)->exists($path)) {
            return;
        }

        try {
            $audio = app(AiTelemetry::class)->forUser($reminder->user_id,
                fn () => $this->tts->synthesize(self::text($reminder), $reminder->voice ?? 'echo', (int) config('reminders.audio.timeout_seconds'), 1));
        } catch (\Throwable $e) {
            $audio = null;
        }
        if (! $audio) {
            Log::warning('contextual_reminder.audio_failed', ['reminder_id' => $reminder->id]);

            return; // fallback: solo texto
        }

        // El recordatorio pudo cerrarse mientras se generaba el audio.
        if (! $this->isOpen($reminder->fresh())) {
            return;
        }
        Storage::disk(self::DISK)->put($path, $audio);
        ContextualReminder::whereKey($reminder->id)->where('state', ContextualReminder::PENDING)->update(['audio_path' => $path]);
        Log::info('contextual_reminder.audio_ready', ['reminder_id' => $reminder->id]);
    }

    public function ready(ContextualReminder $reminder): bool
    {
        return config('reminders.audio_enabled') && $reminder->with_audio && $reminder->audio_path === $this->pathFor($reminder)
            && Storage::disk(self::DISK)->exists($reminder->audio_path);
    }

    private function isOpen(ContextualReminder $reminder): bool
    {
        return $reminder->state === ContextualReminder::PENDING && ! $reminder->isPastExpiry();
    }

    /** Ruta privada elegida por el servidor; cambia si cambia el texto o la voz. */
    private function pathFor(ContextualReminder $reminder): string
    {
        return 'reminders/audio/'.$reminder->id.'-'.substr(hash('sha256', self::text($reminder).'|'.$reminder->voice), 0, 16).'.mp3';
    }
}
