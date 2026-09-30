<?php

namespace App\Console\Commands;

use App\Models\ContextualReminder;
use App\Models\ReminderCommand;
use App\Models\ReminderDelivery;
use App\Services\Reminders\ContextualReminderAudio;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * RC5: retención aprobada de los recordatorios contextuales.
 *  - Contenido (título, contexto, siguiente acción, audio y el contenido de las respuestas
 *    idempotentes guardadas): se retira N días después del cierre (content_retention_days).
 *  - Metadatos e idempotencia: se borran N días después (idempotency_retention_days): recordatorios
 *    cerrados, sus entregas, comandos idempotentes y entregas de rutinas.
 * Nunca toca recordatorios abiertos. Sin --apply solo informa.
 */
class PurgeReminderRetention extends Command
{
    protected $signature = 'reminders:purge {--apply : Ejecutar; sin esto solo informa}';

    protected $description = 'Aplica la retención de los recordatorios contextuales (contenido 7 días, metadatos 30)';

    private const CLOSED = [ContextualReminder::COMPLETED, 'cancelled', 'expired'];

    private const CONTENT_KEYS = ['title', 'context', 'next_action', 'summary'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $contentCutoff = now()->subDays((int) config('reminders.content_retention_days', 7));
        $metadataCutoff = now()->subDays((int) config('reminders.idempotency_retention_days', 30));

        $closedBefore = fn (Carbon $cutoff) => ContextualReminder::whereIn('state', self::CLOSED)
            ->whereRaw('COALESCE(completed_at, cancelled_at, expired_at, updated_at) < ?', [$cutoff]);

        $toRetire = (clone $closedBefore($contentCutoff))->where(fn ($q) => $q->where('context', '!=', '')->orWhere('next_action', '!=', '')
            ->orWhereNotNull('audio_path')->orWhere('title', '!=', ContextualReminder::CONTENT_RETIRED_TITLE));
        $toDelete = $closedBefore($metadataCutoff);
        $oldCommands = ReminderCommand::where('created_at', '<', $metadataCutoff);
        $oldRoutineDeliveries = ReminderDelivery::where('source_type', 'routine')->where('created_at', '<', $metadataCutoff);

        $counts = ['contenido_a_retirar' => (clone $toRetire)->count(), 'recordatorios_a_borrar' => (clone $toDelete)->count(),
            'comandos_a_borrar' => (clone $oldCommands)->count(), 'entregas_rutina_a_borrar' => (clone $oldRoutineDeliveries)->count()];
        $this->table(array_keys($counts), [array_values($counts)]);

        if (! $apply) {
            $this->info('Modo informe: no se modificó nada. Usa --apply para ejecutar.');

            return self::SUCCESS;
        }

        $retired = 0;
        (clone $toRetire)->each(function (ContextualReminder $reminder) use (&$retired) {
            if ($reminder->audio_path) {
                Storage::disk(ContextualReminderAudio::DISK)->delete($reminder->audio_path);
            }
            DB::transaction(function () use ($reminder) {
                $reminder->forceFill(['title' => ContextualReminder::CONTENT_RETIRED_TITLE, 'context' => '', 'next_action' => '', 'audio_path' => null])->saveQuietly();
                ReminderCommand::where('contextual_reminder_id', $reminder->id)->get()->each(function (ReminderCommand $command) {
                    $body = is_array($command->response_body) ? $command->response_body : [];
                    $command->forceFill(['response_body' => array_diff_key($body, array_flip(self::CONTENT_KEYS))])->saveQuietly();
                });
            });
            $retired++;
        });

        $deleted = [
            'recordatorios' => (clone $toDelete)->delete(), // sus entregas se borran en cascada
            'comandos' => (clone $oldCommands)->delete(),
            'entregas_rutina' => (clone $oldRoutineDeliveries)->delete(),
        ];
        Log::info('reminders:purge', ['contenido_retirado' => $retired] + $deleted);
        $this->info("Contenido retirado: {$retired}. Borrados: ".collect($deleted)->map(fn ($n, $k) => "{$k}={$n}")->implode(', ').'.');

        return self::SUCCESS;
    }
}
