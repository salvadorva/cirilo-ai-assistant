<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\UserProfileFact;
use Illuminate\Support\Facades\Log;

class MemoryService
{
    const MIN_CONFIDENCE = 0.6;

    // Confidence mínima para inyectar categorías de interés sin importar recencia
    const INTEREST_HIGH_CONFIDENCE = 0.85;

    // Días dentro de los cuales un fact de interés se considera activo
    const INTEREST_ACTIVE_DAYS = 30;

    // Máx facts de interés por categoría en el prompt
    const MAX_FACTS_PER_INTEREST = 10;

    /**
     * Retorna el perfil completo del usuario agrupado por categoría (todas las categorías).
     * Usado como contexto para el LLM de extracción para evitar re-extraer facts conocidos.
     *
     * @return array ['personal_info' => ['name' => 'Salva', ...], ...]
     */
    public static function getUserProfile(int $userId): array
    {
        return UserProfileFact::where('user_id', $userId)
            ->where('confidence', '>=', self::MIN_CONFIDENCE)
            ->orderBy('confidence', 'desc')
            ->get()
            ->groupBy('category')
            ->map(fn ($facts) => $facts->pluck('value', 'key'))
            ->toArray();
    }

    /**
     * Inserta o actualiza un hecho individual.
     * Si action === 'delete', elimina el hecho.
     */
    public static function upsertFact(int $userId, array $fact): void
    {
        $category = $fact['category'] ?? null;
        $key      = $fact['key'] ?? null;
        $action   = $fact['action'] ?? 'insert';

        if (! $category || ! $key) {
            return;
        }

        // Validar categoría permitida
        $validCategories = array_keys(UserProfileFact::CATEGORY_LABELS);
        if (! in_array($category, $validCategories)) {
            return;
        }

        if ($action === 'delete') {
            UserProfileFact::where('user_id', $userId)
                ->where('category', $category)
                ->where('key', $key)
                ->delete();
            return;
        }

        UserProfileFact::updateOrCreate(
            ['user_id' => $userId, 'category' => $category, 'key' => $key],
            [
                'value'             => $fact['value'] ?? '',
                'confidence'        => max(0.0, min(1.0, (float) ($fact['confidence'] ?? 0.8))),
                'source_type'       => 'extracted',
                'last_mentioned_at' => now(),
            ]
        );
    }

    /**
     * Procesa y guarda el array de hechos devueltos por el LLM.
     */
    public static function saveFacts(int $userId, array $facts): void
    {
        foreach ($facts as $fact) {
            try {
                self::upsertFact($userId, $fact);
            } catch (\Exception $e) {
                Log::warning('MemoryService: error guardando fact', [
                    'user_id' => $userId,
                    'fact'    => $fact,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Construye el bloque de contexto para el system prompt del chat.
     * Incluye perfil estructurado del usuario + conversaciones recientes con pending_items.
     */
    public static function buildContextBlock(int $userId, ?int $excludeConversationId = null): string
    {
        $block = '';

        // ── Perfil del usuario — inyección tiered ────────────────────────────
        $categoryLabels     = UserProfileFact::CATEGORY_LABELS;
        $coreCategories     = UserProfileFact::CORE_CATEGORIES;
        $interestCategories = array_diff(array_keys($categoryLabels), $coreCategories);
        $cutoff             = now()->subDays(self::INTEREST_ACTIVE_DAYS);

        // Core: personal_info, work_context, goals — siempre inyectadas
        $coreProfile = UserProfileFact::where('user_id', $userId)
            ->whereIn('category', $coreCategories)
            ->where('confidence', '>=', self::MIN_CONFIDENCE)
            ->orderBy('confidence', 'desc')
            ->get()
            ->groupBy('category')
            ->map(fn ($facts) => $facts->pluck('value', 'key'))
            ->toArray();

        // Intereses: solo si mencionados recientemente o con alta confianza
        $interestProfile = UserProfileFact::where('user_id', $userId)
            ->whereIn('category', $interestCategories)
            ->where('confidence', '>=', self::MIN_CONFIDENCE)
            ->where(function ($q) use ($cutoff) {
                $q->where('last_mentioned_at', '>=', $cutoff)
                  ->orWhere('confidence', '>=', self::INTEREST_HIGH_CONFIDENCE);
            })
            ->orderBy('confidence', 'desc')
            ->get()
            ->groupBy('category')
            ->map(fn ($facts) => $facts->take(self::MAX_FACTS_PER_INTEREST)->pluck('value', 'key'))
            ->toArray();

        $profile = array_merge($coreProfile, $interestProfile);

        if (! empty($profile)) {
            $block .= "\n### Perfil del Usuario:\n";
            $block .= "Usa esta información para personalizar tus respuestas.\n";

            foreach ($profile as $category => $facts) {
                $label  = $categoryLabels[$category] ?? ucfirst($category);
                $block .= "\n**{$label}**:\n";
                foreach ($facts as $key => $value) {
                    $readableKey = str_replace('_', ' ', $key);
                    $block .= "- {$readableKey}: {$value}\n";
                }
            }
        }

        // ── Conversaciones recientes ──────────────────────────────────────────
        $recentConversations = Conversation::where('user_id', $userId)
            ->when($excludeConversationId, fn ($q) => $q->where('id', '!=', $excludeConversationId))
            ->where('updated_at', '>=', now()->subDays(60))
            ->orderBy('updated_at', 'desc')
            ->limit(3)
            ->get();

        if ($recentConversations->isNotEmpty()) {
            $block .= "\n### Conversaciones Recientes:\n";
            $block .= "Usa esta información si el usuario pregunta por temas tratados antes.\n";
            $block .= "Nota: no incluye la conversación actual (esa viene en el historial de mensajes).\n";

            foreach ($recentConversations as $conv) {
                $contentData = json_decode($conv->content ?? '{}', true);
                $msgs        = $contentData['messages'] ?? [];

                if (empty($msgs) && ! $conv->summary) {
                    continue;
                }

                $when  = $conv->updated_at->diffForHumans();
                $block .= "\n**{$conv->title}** ({$when}):\n";

                if ($conv->summary) {
                    $block .= $conv->summary . "\n";

                    // Mensajes posteriores al último resumen (máx 6)
                    $newMsgs = array_slice($msgs, $conv->summarized_message_count ?? 0);
                    $newMsgs = array_slice($newMsgs, -6);
                    foreach ($newMsgs as $msg) {
                        $role  = ($msg['role'] ?? '') === 'user' ? 'Usuario' : 'Asistente';
                        $text  = mb_substr($msg['content'] ?? '', 0, 200);
                        $block .= "- {$role}: {$text}\n";
                    }
                } else {
                    foreach (array_slice($msgs, -4) as $msg) {
                        $role  = ($msg['role'] ?? '') === 'user' ? 'Usuario' : 'Asistente';
                        $text  = mb_substr($msg['content'] ?? '', 0, 200);
                        $block .= "- {$role}: {$text}\n";
                    }
                }

                // Pendientes de esa conversación
                $pending = $conv->pending_items;
                if (! empty($pending)) {
                    $block .= "  *Pendiente de esta conversación:*\n";
                    foreach ($pending as $item) {
                        $block .= "  - {$item}\n";
                    }
                }
            }
        }

        return $block;
    }
}
