<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\UserProfileFact;
use App\Services\ConversationSummaryService;
use App\Services\ExplicitMemoryService;
use App\Services\MemoryService;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\SecurityTestCase;

/** F4-08: una instrucción explícita de memoria sobrevive a un intercambio breve y a conversaciones nuevas. */
class ExplicitMemoryTest extends SecurityTestCase
{
    private function text(): Response
    {
        return new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '¡Perfecto!']]]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 5]]));
    }

    private function preferredName(int $userId): ?UserProfileFact
    {
        return UserProfileFact::where('user_id', $userId)->where('category', 'personal_info')->where('key', 'preferred_name')->first();
    }

    public function test_web_chat_persists_preferred_name_and_recovers_it_after_more_than_four_new_conversations(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $this->provider->append($this->text());

        $this->postJson('/generate-text', ['prompt' => 'Llámame Salva.', 'generateAudio' => false])->assertOk();

        $fact = $this->preferredName($user->id);
        $this->assertNotNull($fact);
        $this->assertSame(['Salva', 1.0, 'user_explicit'], [$fact->value, $fact->confidence, $fact->source_type]);
        // Ya forma parte del contexto del mismo turno.
        $this->assertStringContainsString('preferred name: Salva', (string) $this->provider->getLastRequest()->getBody());

        foreach (range(1, 5) as $i) {
            Conversation::create(['user_id' => $user->id, 'title' => "Tema {$i}", 'summary' => "Otro tema {$i}",
                'content' => json_encode(['messages' => [['role' => 'user', 'content' => "Pregunta {$i}"]]])]);
        }
        $this->assertStringContainsString('preferred name: Salva', MemoryService::buildContextBlock($user->id));
        $this->assertStringNotContainsString('Salva', MemoryService::buildContextBlock($this->user()->id));
    }

    public function test_mobile_chat_persists_preferred_name(): void
    {
        $user = $this->user();
        $this->provider->append($this->text());

        $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('android')->plainTextToken])
            ->postJson('/api/mobile/chat', ['prompt' => 'Por favor, puedes llamarme Mariana', 'generateAudio' => false])->assertOk();

        $this->assertSame('Mariana', $this->preferredName($user->id)?->value);
    }

    public function test_a_new_explicit_preference_replaces_the_previous_one(): void
    {
        $user = $this->user();
        ExplicitMemoryService::capture($user->id, 'Llámame Salva');
        ExplicitMemoryService::capture($user->id, 'Mejor llámame Ana María López, por favor.');

        $this->assertSame(1, UserProfileFact::where('user_id', $user->id)->count());
        $this->assertSame('Ana María López', $this->preferredName($user->id)->value);
    }

    public static function notNames(): array
    {
        return [
            'pregunta' => ['¿Cómo prefiero que me llames?'],
            'tiempo' => ['Llámame mañana a las 8'],
            'condición' => ['llámame cuando llegues'],
            'tercero' => ['Dile a Juan que me llame'],
            'frase larga' => ['Llámame la atención si me distraigo con el teléfono'],
            'negación' => ['No me llames Salva'],
            'nombre en mayúsculas al final' => ['Llámame loco, pero creo que funciona'],
        ];
    }

    #[DataProvider('notNames')]
    public function test_ambiguous_phrases_do_not_create_facts(string $prompt): void
    {
        $user = $this->user();
        $this->assertNull(ExplicitMemoryService::capture($user->id, $prompt));
        $this->assertSame(0, UserProfileFact::count());
    }

    public function test_short_exchange_with_explicit_memory_cue_runs_extraction(): void
    {
        $user = $this->user();
        config(['services.openai.api_key' => 'fake-test-key']);
        $this->provider->append(new Response(200, [], json_encode(['choices' => [['message' => ['content' => json_encode([
            'summary' => 'Prefiere reuniones después de las 10.',
            'extracted_facts' => [['category' => 'preferences', 'key' => 'meeting_time', 'value' => 'Después de las 10', 'confidence' => 0.9, 'action' => 'insert']],
        ])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]])));
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Breve', 'content' => '{}']);

        app(ConversationSummaryService::class)->maybeSummarize($conversation, [
            ['role' => 'user', 'content' => 'Recuerda que prefiero reuniones después de las 10'],
            ['role' => 'assistant', 'content' => 'De acuerdo'],
        ]);

        $this->assertSame('Después de las 10', UserProfileFact::where('user_id', $user->id)->where('key', 'meeting_time')->value('value'));
        $this->assertSame(2, $conversation->fresh()->summarized_message_count);
    }

    public function test_short_exchange_without_cue_does_not_call_the_provider(): void
    {
        $user = $this->user();
        config(['services.openai.api_key' => 'fake-test-key']);
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Breve', 'content' => '{}']);

        app(ConversationSummaryService::class)->maybeSummarize($conversation, [
            ['role' => 'user', 'content' => '¿Qué hora es?'],
            ['role' => 'assistant', 'content' => 'Las 10'],
        ]);

        $this->assertNull($this->provider->getLastRequest());
        $this->assertSame(0, UserProfileFact::count());
    }

    public function test_assistant_text_is_not_an_explicit_user_instruction(): void
    {
        config(['services.openai.api_key' => 'fake-test-key']);
        $conversation = Conversation::create(['user_id' => $this->user()->id, 'title' => 'Breve', 'content' => '{}']);

        app(ConversationSummaryService::class)->maybeSummarize($conversation, [
            ['role' => 'user', 'content' => 'Hola'],
            ['role' => 'assistant', 'content' => 'Recuerda que puedes pedirme lo que quieras'],
        ]);

        $this->assertNull($this->provider->getLastRequest());
    }
}
