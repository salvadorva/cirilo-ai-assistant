<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use App\Models\UserProfileFact;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use App\Support\AiLog as Log;

class ConversationSummaryService
{
    const RESUMEN_CADA_N_MENSAJES = 10;
    const RESUMEN_MINIMO_MENSAJES = 4;

    /**
     * Genera o actualiza el resumen si hay suficientes mensajes nuevos.
     * Con $force=true ignora el umbral (usado para cerrar conversaciones abiertas).
     */
    public function maybeSummarize(Conversation $conversation, array $messages, bool $force = false): void
    {
        $messageCount = count($messages);

        // F4-08: un intercambio breve con instrucción explícita («recuerda que…») sí se procesa.
        if ($messageCount < self::RESUMEN_MINIMO_MENSAJES && ! $this->hasExplicitMemoryCue($messages)) {
            return;
        }

        $newMessages = $messageCount - ($conversation->summarized_message_count ?? 0);

        if (! $force && $newMessages < self::RESUMEN_CADA_N_MENSAJES && $conversation->summary) {
            return;
        }

        // Nada nuevo que resumir: tampoco se permite que un resumen de menos mensajes pise uno más reciente.
        if ($newMessages <= 0) {
            return;
        }

        $apiKey = config('services.openai.api_key');
        if (! $apiKey) {
            return;
        }

        $currentProfile = MemoryService::getUserProfile($conversation->user_id);

        $result = app(AiTelemetry::class)->forUser($conversation->user_id, fn () => $this->generateSummary(
            $messages,
            $apiKey,
            $conversation->summary,
            $conversation->summarized_message_count ?? 0,
            $currentProfile,
            MemoryService::forgottenKeys($conversation->user_id)
        ));

        if (! $result) {
            return;
        }

        // Otra petición pudo guardar un resumen que cubre más mensajes mientras el modelo respondía.
        $applied = DB::transaction(function () use ($conversation, $result, $messageCount) {
            $current = Conversation::whereKey($conversation->id)->lockForUpdate()->first();
            if (! $current || ($current->summarized_message_count ?? 0) >= $messageCount) {
                return false;
            }

            $current->summary                  = $result['summary'];
            $current->summarized_message_count = $messageCount;
            $current->topics                   = $result['topics'] ?? null;
            $current->decisions                = $result['decisions'] ?? null;
            $current->pending_items            = $result['pending_items'] ?? null;
            if (! empty($result['title'])) {
                $current->title = $result['title'];
            }
            $current->saveQuietly();
            $conversation->setRawAttributes($current->getAttributes(), true);

            return true;
        });

        // F4-06: con la extracción apagada se sigue resumiendo, pero no se aprenden hechos.
        $extractionEnabled = (bool) (User::whereKey($conversation->user_id)->value('memory_extraction_enabled') ?? true);
        // F6-02: los puntos pendientes del resumen son sugerencias para revisar, no compromisos.
        if ($applied && ! empty($result['pending_items']) && is_array($result['pending_items'])) {
            $owner = User::find($conversation->user_id);
            if ($owner) {
                app(\App\Services\Tasks\TaskService::class)->suggest($owner, $result['pending_items'], $conversation->id);
            }
        }

        if ($applied && $extractionEnabled && ! empty($result['extracted_facts'])) {
            MemoryService::saveFacts($conversation->user_id, $result['extracted_facts'], $conversation->id);
        }
    }

    /**
     * Sumariza conversaciones previas del usuario que quedaron sin cerrar.
     * Se llama al crear una conversación nueva.
     */
    public function summarizeOpenConversations(int $userId, int $excludeId): void
    {
        $apiKey = config('services.openai.api_key');
        if (! $apiKey) {
            return;
        }

        $conversations = Conversation::where('user_id', $userId)
            ->where('id', '!=', $excludeId)
            ->latest('updated_at')
            ->limit(5)
            ->get();

        foreach ($conversations as $conv) {
            $contentData  = json_decode($conv->content ?? '{}', true);
            $messages     = $contentData['messages'] ?? [];
            $unsummarized = count($messages) - ($conv->summarized_message_count ?? 0);

            if ($unsummarized > 0 && count($messages) >= self::RESUMEN_MINIMO_MENSAJES) {
                $this->maybeSummarize($conv, $messages, true);
            }
        }
    }

    /**
     * Llama a gpt-4o-mini para generar/actualizar resumen y extraer facts del usuario.
     * Modo incremental si ya existe resumen previo.
     */
    public function generateSummary(
        array $messages,
        string $apiKey,
        ?string $existingSummary = null,
        int $fromIndex = 0,
        array $currentProfile = [],
        array $forgottenKeys = []
    ): ?array {
        $isIncremental = $existingSummary && $fromIndex > 0;

        $categoryList = implode('|', array_keys(UserProfileFact::CATEGORY_LABELS));

        $jsonSchema = '{'
            . '"title": "Título de máximo 8 palabras",'
            . '"summary": "Resumen de máximo 150 palabras con decisiones, info relevante, preferencias y temas. Sin saludos.",'
            . '"topics": ["tema1", "tema2"],'
            . '"decisions": ["decisión tomada"] or null,'
            . '"pending_items": ["cosa pendiente"] or null,'
            . '"extracted_facts": ['
            . '{"category": "' . $categoryList . '",'
            . '"key": "snake_case_key","value": "valor conciso","confidence": 0.9,"action": "insert|update|delete"}'
            . '] (máximo 6, solo los más relevantes, o [] si no hay nada nuevo)'
            . '}';

        $profileContext = '';
        if (! empty($currentProfile)) {
            $profileContext = "\n\nPERFIL ACTUAL DEL USUARIO (no re-extraer lo que ya está con el mismo valor):\n"
                . json_encode($currentProfile, JSON_UNESCAPED_UNICODE);
        }
        if (! empty($forgottenKeys)) {
            $profileContext .= "\n\nDATOS QUE EL USUARIO PIDIÓ OLVIDAR (no volver a extraerlos):\n".implode(', ', $forgottenKeys);
        }

        $extractionRules = "\n\nREGLAS PARA extracted_facts:"
            . "\n- Solo información EXPLÍCITA o fuertemente implícita del usuario"
            . "\n- NO extraer lo que propuso o sugirió el asistente, salvo que el usuario lo aceptara de forma explícita"
            . "\n- Si el usuario contradice o corrige un dato del PERFIL ACTUAL, usa action=update con el valor nuevo (misma category y key)"
            . "\n- NO inventes ni asumas"
            . "\n- NO extraer: contraseñas, datos bancarios, números de tarjeta"
            . "\n- confidence: 0.9=explícito, 0.7=implícito claro, 0.5=inferido"
            . "\n- action: insert=nuevo, update=cambia valor, delete=ya no aplica"
            . "\n- Usa la categoría más específica disponible (p.ej. 'sports' para fútbol, 'studies' para cursos)"
            . "\n- Máximo 6 hechos — solo los más relevantes y nuevos";

        if ($isIncremental) {
            $newMsgs    = array_slice($messages, $fromIndex);
            $transcript = $this->buildTranscript($newMsgs);
            $systemContent = 'Actualiza el resumen e identifica hechos nuevos sobre el usuario. '
                . 'Responde SOLO con JSON válido con esta estructura: ' . $jsonSchema
                . $profileContext . $extractionRules;
            $userContent = "Resumen actual:\n{$existingSummary}\n\nMensajes nuevos:\n{$transcript}";
        } else {
            $transcript    = $this->buildTranscript($messages);
            $systemContent = 'Analiza esta conversación y responde SOLO con JSON válido con esta estructura: '
                . $jsonSchema . $profileContext . $extractionRules;
            $userContent = $transcript;
        }

        try {
            $client   = app(AiTransport::class)->client(['timeout' => 15]);
            $response = app(InteractionTracker::class)->measure('summary_memory', fn () => $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => config('ai.models.summary'),
                    'temperature' => 0.3,
                    'max_tokens'  => 700,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemContent],
                        ['role' => 'user',   'content' => $userContent],
                    ],
                ],
            ]));

            $data    = json_decode($response->getBody(), true);
            $content = trim($data['choices'][0]['message']['content'] ?? '');
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $parsed = json_decode($content, true);

            if (! is_array($parsed) || empty($parsed['summary'])) {
                return null;
            }

            return [
                'title'           => mb_substr(trim($parsed['title'] ?? ''), 0, 100),
                'summary'         => trim($parsed['summary']),
                'topics'          => $parsed['topics'] ?? null,
                'decisions'       => $parsed['decisions'] ?? null,
                'pending_items'   => $parsed['pending_items'] ?? null,
                'extracted_facts' => $parsed['extracted_facts'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::warning('ConversationSummaryService: error generando resumen', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function hasExplicitMemoryCue(array $messages): bool
    {
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'user' && ExplicitMemoryService::hasCue($msg['content'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function buildTranscript(array $messages): string
    {
        $transcript = '';
        foreach ($messages as $msg) {
            $role        = ($msg['role'] ?? '') === 'user' ? 'Usuario' : 'Asistente';
            $text        = mb_substr($msg['content'] ?? '', 0, 600);
            $transcript .= "{$role}: {$text}\n";
        }
        return $transcript;
    }
}
