<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\ConversationSummaryService;
use Illuminate\Console\Command;

class SummarizeStaleConversations extends Command
{
    protected $signature   = 'memory:summarize-stale';
    protected $description = 'Sumariza conversaciones con mensajes nuevos que llevan +2h sin actividad';

    public function __construct(private ConversationSummaryService $summaryService)
    {
        parent::__construct();
    }

    // Tope de costo: máximo de conversaciones resumidas por corrida y antigüedad máxima.
    const MAX_POR_CORRIDA = 20;

    const DIAS_MAXIMOS = 7;

    public function handle(): int
    {
        $processed = 0;

        // Solo conversaciones recientes: el primer arranque no resume de golpe todo el histórico.
        Conversation::where('updated_at', '<', now()->subHours(2))
            ->where('updated_at', '>=', now()->subDays(self::DIAS_MAXIMOS))
            ->orderByDesc('updated_at')
            ->each(function (Conversation $conv) use (&$processed) {
                $messages = json_decode($conv->content ?? '{}', true)['messages'] ?? [];
                if (count($messages) < ConversationSummaryService::RESUMEN_MINIMO_MENSAJES
                    || count($messages) <= ($conv->summarized_message_count ?? 0)) {
                    return true;
                }

                $this->summaryService->maybeSummarize($conv, $messages, true);

                return ++$processed < self::MAX_POR_CORRIDA;
            });

        $this->info("Procesadas {$processed} conversaciones.");

        return Command::SUCCESS;
    }
}
