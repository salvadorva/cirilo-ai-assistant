<?php

namespace App\Console\Commands;

use App\Models\FocusSlot;
use App\Services\FcmService;
use App\Services\TtsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendFocusMessages extends Command
{
    protected $signature = 'focus:send-messages';

    protected $description = 'Envía mensajes de enfoque por voz (push FCM data-only) según los focus_slots';

    // Zona horaria de los slots (la hora guardada es local del usuario)
    const TIMEZONE = 'America/Guatemala';

    // Ventana de recuperación: si el scheduler falla un ciclo, el slot
    // todavía se envía hasta estos minutos después de su hora
    const CATCHUP_MINUTES = 10;

    public function __construct(private FcmService $fcm, private TtsService $tts)
    {
        parent::__construct();
    }

    public function handle()
    {
        $now = Carbon::now(self::TIMEZONE);
        $this->info("Verificando focus slots [{$now->format('Y-m-d H:i:s')}]...");

        $slots = FocusSlot::where('enabled', true)->get();

        foreach ($slots as $slot) {
            if (! in_array($now->isoWeekday(), $slot->days ?? [], true)) {
                continue;
            }

            $slotAt = Carbon::createFromFormat(
                'Y-m-d H:i',
                $now->format('Y-m-d').' '.$slot->time,
                self::TIMEZONE
            );

            if ($now->lt($slotAt) || $now->gt($slotAt->copy()->addMinutes(self::CATCHUP_MINUTES))) {
                continue;
            }

            // Ya enviado para la ocurrencia de hoy
            if ($slot->last_sent_at && $slot->last_sent_at->timezone(self::TIMEZONE)->gte($slotAt)) {
                continue;
            }

            // Marcar antes de enviar: el TTS tarda segundos y otro ciclo podría solaparse
            $slot->update(['last_sent_at' => $now]);

            // Slots de solo texto no generan audio; la app muestra la
            // notificación sin botón ▶ y no auto-reproduce
            $audioUrl = null;
            if ($slot->with_audio) {
                // El mp3 se cachea por slot: el modelo limpia audio_path si
                // cambia mensaje/voz, así que aquí solo se genera si falta
                if ($slot->audio_path && Storage::disk('public')->exists($slot->audio_path)) {
                    $audioUrl = Storage::disk('public')->url($slot->audio_path);
                } else {
                    $path = sprintf('audio/focus/slot-%d-%s.mp3', $slot->id, substr(md5($slot->message.'|'.$slot->voice), 0, 8));
                    $audioUrl = $this->tts->generateMp3($slot->message, $slot->voice, $path);
                    if ($audioUrl) {
                        $slot->update(['audio_path' => $path]);
                    } else {
                        Log::warning('[Focus] TTS falló, se envía solo texto', ['slot_id' => $slot->id]);
                    }
                }
            }

            $sent = $this->fcm->sendToUser(
                $slot->user_id,
                $slot->title,
                $slot->message,
                [
                    'type'      => 'focus_message',
                    'slot_id'   => $slot->id,
                    'audio_url' => $audioUrl ?? '',
                ],
                dataOnly: true
            );

            if ($sent) {
                $this->line("Mensaje de enfoque enviado: {$slot->title} ({$slot->time})");
                Log::info('[Focus] Mensaje enviado', ['slot_id' => $slot->id, 'title' => $slot->title, 'audio' => (bool) $audioUrl]);
            } else {
                $this->error("Falló el envío FCM: {$slot->title}");
                Log::error('[Focus] Envío FCM falló para todos los tokens', ['slot_id' => $slot->id]);
            }
        }

        $this->info('Verificación completada.');
    }
}
