<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\UserProfileFact;
use App\Services\ConversationSummaryService;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Schedule;
use Tests\SecurityTestCase;

/** F4-03: el resumen corre de verdad (programado y desde el móvil) y un resumen atrasado no pisa uno nuevo. */
class ConversationSummaryFlowTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.api_key' => 'fake-test-key']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function summary(string $text = 'Resumen', array $facts = []): Response
    {
        return new Response(200, [], json_encode(['choices' => [['message' => ['content' => json_encode(['summary' => $text, 'extracted_facts' => $facts])]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]]));
    }

    private function messages(int $count, string $content = 'Mensaje'): array
    {
        return array_map(fn ($i) => ['role' => $i % 2 ? 'assistant' : 'user', 'content' => "{$content} {$i}"], range(0, $count - 1));
    }

    private function conversation(int $userId, int $messages, array $attributes = []): Conversation
    {
        return Conversation::create($attributes + ['user_id' => $userId, 'title' => 'Tema', 'type' => 'chat',
            'content' => json_encode(['messages' => $this->messages($messages)])]);
    }

    public function test_stale_summaries_are_registered_in_the_effective_scheduler(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode("\n");

        $this->assertStringContainsString('memory:summarize-stale', $commands);
    }

    public function test_stale_command_is_capped_per_run_and_skips_old_conversations(): void
    {
        $user = $this->user();
        foreach (range(1, 22) as $i) {
            $this->conversation($user->id, 4)->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();
        }
        $old = $this->conversation($user->id, 4);
        $old->forceFill(['updated_at' => now()->subDays(30)])->saveQuietly();
        foreach (range(1, 22) as $i) {
            $this->provider->append($this->summary());
        }

        $this->artisan('memory:summarize-stale')->assertExitCode(0);

        $this->assertSame(2, $this->provider->count(), 'Máximo 20 llamadas por corrida.');
        $this->assertSame(20, Conversation::whereNotNull('summary')->count());
        $this->assertNull($old->fresh()->summary);
    }

    public function test_mobile_chat_triggers_summary_after_the_response(): void
    {
        $user = $this->user();
        $this->provider->append(
            new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'De acuerdo']]]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 5]])),
            $this->summary('Prefiere reuniones después de las 10.', [['category' => 'preferences', 'key' => 'meeting_time', 'value' => 'Después de las 10', 'confidence' => 0.9]])
        );

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('android')->plainTextToken])
            ->postJson('/api/mobile/chat', ['prompt' => 'Recuerda que prefiero reuniones después de las 10', 'generateAudio' => false])->assertOk();

        $conversation = Conversation::find($response->json('conversation_id'));
        $this->assertSame(2, $conversation->summarized_message_count);
        $this->assertSame('Después de las 10', UserProfileFact::where('user_id', $user->id)->where('key', 'meeting_time')->value('value'));
    }

    public function test_mobile_summary_failure_does_not_break_the_chat_response(): void
    {
        $user = $this->user();
        $this->provider->append(
            new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Listo']]]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 5]])),
            new Response(500, [], 'error')
        );

        $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('android')->plainTextToken])
            ->postJson('/api/mobile/chat', ['prompt' => 'Recuerda que me gusta el café', 'generateAudio' => false])
            ->assertOk()->assertJsonPath('reply', 'Listo');
    }

    public function test_an_older_summary_never_overwrites_a_newer_one(): void
    {
        $conversation = $this->conversation($this->user()->id, 12, ['summary' => 'Resumen nuevo', 'summarized_message_count' => 12]);
        $this->provider->append($this->summary('Resumen viejo'));

        app(ConversationSummaryService::class)->maybeSummarize($conversation, $this->messages(4), true);

        $this->assertSame(1, $this->provider->count(), 'No gasta en un resumen que ya está cubierto.');
        $this->assertSame([12, 'Resumen nuevo'], [$conversation->fresh()->summarized_message_count, $conversation->fresh()->summary]);
    }

    public function test_a_concurrent_newer_summary_wins_the_race(): void
    {
        $conversation = $this->conversation($this->user()->id, 4);
        // Mientras el modelo responde, otra petición guarda un resumen que cubre más mensajes.
        $this->provider->append(function () use ($conversation) {
            Conversation::whereKey($conversation->id)->update(['summary' => 'Resumen de 14', 'summarized_message_count' => 14]);

            return $this->summary('Resumen de 4', [['category' => 'preferences', 'key' => 'late', 'value' => 'x', 'confidence' => 0.9]]);
        });

        app(ConversationSummaryService::class)->maybeSummarize($conversation, $this->messages(4), true);

        $this->assertSame([14, 'Resumen de 14'], [$conversation->fresh()->summarized_message_count, $conversation->fresh()->summary]);
        $this->assertSame(0, UserProfileFact::count(), 'Los hechos de un resumen descartado tampoco se guardan.');
    }
}
