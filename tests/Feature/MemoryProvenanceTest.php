<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\UserProfileFact;
use App\Services\ConversationSummaryService;
use App\Services\ExplicitMemoryService;
use GuzzleHttp\Psr7\Response;
use Tests\SecurityTestCase;

/** F4-05: origen y actualización de cada hecho; lo que propone el asistente no es un hecho del usuario. */
class MemoryProvenanceTest extends SecurityTestCase
{
    private function summarize(Conversation $conversation, array $facts): string
    {
        config(['services.openai.api_key' => 'fake-test-key']);
        $body = new \ArrayObject;
        $this->provider->append(function ($request) use ($body, $facts) {
            $body['text'] = json_encode(json_decode((string) $request->getBody(), true), JSON_UNESCAPED_UNICODE);

            return new Response(200, [], json_encode(['choices' => [['message' => ['content' => json_encode(['summary' => 'R', 'extracted_facts' => $facts])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]]));
        });
        app(ConversationSummaryService::class)->maybeSummarize($conversation->fresh(), array_fill(0, 4, ['role' => 'user', 'content' => 'Mensaje']), true);

        return $body['text'];
    }

    private function conversation(int $userId): Conversation
    {
        return Conversation::create(['user_id' => $userId, 'title' => 'Origen', 'type' => 'chat', 'content' => '{}']);
    }

    public function test_extracted_facts_record_their_source_conversation_and_last_update(): void
    {
        $user = $this->user();
        $first = $this->conversation($user->id);
        $this->summarize($first, [['category' => 'sports', 'key' => 'team', 'value' => 'Municipal', 'confidence' => 0.9]]);
        $this->assertSame($first->id, UserProfileFact::sole()->source_conversation_id);

        // Contradicción posterior: el dato se corrige y registra de dónde vino la corrección.
        $second = $this->conversation($user->id);
        $this->travel(2)->days();
        $this->summarize($second, [['category' => 'sports', 'key' => 'team', 'value' => 'Comunicaciones', 'confidence' => 0.9, 'action' => 'update']]);

        $fact = UserProfileFact::sole();
        $this->assertSame(['Comunicaciones', $second->id], [$fact->value, $fact->source_conversation_id]);
        $this->assertTrue($fact->last_mentioned_at->isToday());
    }

    public function test_the_extractor_is_told_to_ignore_assistant_proposals_and_to_correct_contradictions(): void
    {
        $prompt = $this->summarize($this->conversation($this->user()->id), []);

        $this->assertStringContainsString('propuso o sugirió el asistente', $prompt);
        $this->assertStringContainsString('contradice', $prompt);
    }

    public function test_explicit_capture_records_the_conversation_and_deleting_it_keeps_the_fact(): void
    {
        $user = $this->user();
        $conversation = $this->conversation($user->id);

        ExplicitMemoryService::capture($user->id, 'Llámame Salva', $conversation->id);
        $this->assertSame($conversation->id, UserProfileFact::sole()->source_conversation_id);

        $conversation->delete();
        $this->assertSame(['Salva', null], [UserProfileFact::sole()->value, UserProfileFact::sole()->source_conversation_id]);
    }

    public function test_the_memory_api_exposes_the_origin(): void
    {
        $user = $this->user();
        $conversation = $this->conversation($user->id);
        ExplicitMemoryService::capture($user->id, 'Llámame Salva', $conversation->id);

        $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('android')->plainTextToken])->getJson('/api/mobile/memory')
            ->assertJsonPath('facts.personal_info.0.source_conversation_id', $conversation->id)
            ->assertJsonPath('facts.personal_info.0.source_type', ExplicitMemoryService::SOURCE);
    }
}
