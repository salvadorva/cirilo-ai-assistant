<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\ConversationHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F4-01: reconcilia conversaciones de chat guardadas antes de que la tabla messages fuera la fuente
 * principal (el guardado web solo actualizaba el JSON). Sin --apply solo informa.
 *
 * - Tabla atrasada (sus mensajes son el inicio del JSON): agrega lo que falta a la tabla.
 * - JSON atrasado (el JSON es el inicio de la tabla): reconstruye el JSON desde la tabla.
 * - Divergente: se informa y no se toca.
 */
class BackfillConversationMessages extends Command
{
    protected $signature = 'conversations:backfill-messages {--apply : Escribir los cambios; sin esto solo informa}';

    protected $description = 'Reconcilia conversations.content con la tabla messages (F4-01)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $counts = ['consistentes' => 0, 'tabla_atrasada' => 0, 'json_atrasado' => 0, 'divergentes' => 0, 'mensajes_agregados' => 0];
        $divergent = [];

        Conversation::where('type', 'chat')->orderBy('id')->chunkById(200, function ($conversations) use ($apply, &$counts, &$divergent) {
            foreach ($conversations as $conversation) {
                $json = ConversationHistory::normalize(json_decode($conversation->content ?? '{}', true)['messages'] ?? []);
                $table = $conversation->messages()->orderBy('id')->get(['role', 'content'])
                    ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->all();
                $jsonPairs = array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $json);

                if ($jsonPairs === $table) {
                    $counts['consistentes']++;
                } elseif (count($table) < count($jsonPairs) && array_slice($jsonPairs, 0, count($table)) === $table) {
                    $counts['tabla_atrasada']++;
                    $missing = array_slice($json, count($table));
                    $counts['mensajes_agregados'] += count($missing);
                    if ($apply) {
                        DB::transaction(function () use ($conversation, $missing) {
                            foreach ($missing as $message) {
                                ConversationHistory::insert($conversation, $message);
                            }
                            // Sin tocar updated_at: no debe cambiar el orden de las conversaciones recientes.
                            $conversation->timestamps = false;
                            ConversationHistory::refreshContent($conversation);
                        });
                    }
                } elseif (count($jsonPairs) < count($table) && array_slice($table, 0, count($jsonPairs)) === $jsonPairs) {
                    $counts['json_atrasado']++;
                    if ($apply) {
                        $conversation->timestamps = false;
                        ConversationHistory::refreshContent($conversation);
                    }
                } else {
                    $counts['divergentes']++;
                    $divergent[] = $conversation->id;
                }
            }
        });

        $this->table(array_keys($counts), [array_values($counts)]);
        if ($divergent) {
            $this->warn('Conversaciones divergentes sin tocar (ids): '.implode(', ', array_slice($divergent, 0, 50)).(count($divergent) > 50 ? ' …' : ''));
        }
        $this->info($apply ? 'Cambios aplicados.' : 'Modo informe: no se escribió nada. Usa --apply para aplicar.');

        return self::SUCCESS;
    }
}
