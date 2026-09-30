<?php

namespace App\Services;

use App\Models\Conversation;

/**
 * F4-02: historial que se envía al modelo en cada turno.
 *
 * - Fuente: el historial del cliente; si no manda, los mensajes guardados de la conversación
 *   (la tabla messages es la fuente principal desde F4-01).
 * - La pregunta actual va una sola vez: se quita si el historial ya termina con ella.
 * - Presupuesto: máximo de mensajes y de tokens estimados; se descartan los más antiguos. Lo
 *   descartado sigue representado por el resumen de la conversación activa (MemoryService).
 */
class ChatContext
{
    public static function history(array $clientHistory, ?Conversation $conversation, string $prompt): array
    {
        $history = ConversationHistory::normalize($clientHistory);
        if ($history === [] && $conversation) {
            $limit = (int) config('ai.context.history_messages', 10) + 1;
            $history = $conversation->messages()->orderByDesc('id')->limit($limit)->get(['role', 'content'])
                ->reverse()->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->values()->all();
        }

        $last = end($history);
        if ($last && $last['role'] === 'user' && trim($last['content']) === trim($prompt)) {
            array_pop($history);
        }

        $history = array_slice(array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $history),
            -max(0, (int) config('ai.context.history_messages', 10)));

        $budget = (int) config('ai.context.history_budget_tokens', 4000);
        while ($history !== [] && self::tokens($history) > $budget) {
            array_shift($history);
        }

        return $history;
    }

    public static function tokens(array $messages): int
    {
        return array_sum(array_map(fn ($m) => (int) ceil(mb_strlen($m['content'] ?? '') / 4), $messages));
    }
}
