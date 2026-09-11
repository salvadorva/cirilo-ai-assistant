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

    public function handle(): int
    {
        $cutoff = now()->subHours(2);

        $conversations = Conversation::where('updated_at', '<', $cutoff)->get();

        $processed = 0;

        foreach ($conversations as $conv) {
            $contentData  = json_decode($conv->content ?? '{}', true);
            $messages     = $contentData['messages'] ?? [];
            $messageCount = count($messages);
            $summarized   = $conv->summarized_message_count ?? 0;

            if ($messageCount < ConversationSummaryService::RESUMEN_MINIMO_MENSAJES) {
                continue;
            }

            if ($messageCount <= $summarized) {
                continue;
            }

            $this->summaryService->maybeSummarize($conv, $messages, true);
            $processed++;
        }

        $this->info("Procesadas {$processed} conversaciones.");

        return Command::SUCCESS;
    }
}
