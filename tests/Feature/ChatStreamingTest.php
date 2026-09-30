<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Tests\SecurityTestCase;

/** F5: texto primero (streaming opcional), voz aparte y errores del proveedor centralizados. */
class ChatStreamingTest extends SecurityTestCase
{
    private User $owner;

    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-21 08:00:00', 'America/Guatemala'));
        config(['services.openai.api_key' => 'fake-test-key', 'ai.retry.max' => 1, 'ai.retry.delay_ms' => 0]);
        $this->owner = $this->user();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Cuerpo SSE como lo emite la Responses API. */
    private function sse(array $deltas, ?array $output = null, array $usage = ['input_tokens' => 120, 'output_tokens' => 8]): Response
    {
        $text = implode('', $deltas);
        $output ??= [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]]];
        $body = '';
        foreach ($deltas as $delta) {
            $body .= "event: response.output_text.delta\ndata: ".json_encode(['type' => 'response.output_text.delta', 'delta' => $delta])."\n\n";
        }
        $body .= "event: response.completed\ndata: ".json_encode(['type' => 'response.completed', 'response' => ['model' => 'gpt-4.1-2025-04-14', 'output' => $output, 'usage' => $usage]])."\n\n";

        return new Response(200, ['Content-Type' => 'text/event-stream'], $body);
    }

    private function capture(Response $response): void
    {
        $this->provider->append(function ($request) use ($response) {
            $this->requests[] = json_decode((string) $request->getBody(), true);

            return $response;
        });
    }

    private function streamChat(string $prompt, array $extra = []): array
    {
        $response = $this->flushHeaders()->actingAs($this->owner)->withHeaders(['Accept' => 'text/event-stream'])
            ->post('/generate-text', ['prompt' => $prompt, 'generateAudio' => false] + $extra);
        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $events = [];
        foreach (preg_split("/\n\n/", trim($response->streamedContent())) as $block) {
            if (preg_match('/^event: (\S+)\ndata: (.*)$/s', $block, $m)) {
                $events[] = [$m[1], json_decode($m[2], true)];
            }
        }

        return $events;
    }

    public function test_streaming_sends_text_deltas_before_the_final_contract(): void
    {
        $this->capture($this->sse(['Hola, ', 'Salva.']));

        $events = $this->streamChat('Hola');

        $names = array_column($events, 0);
        $this->assertSame(['delta', 'delta', 'done'], $names);
        $this->assertSame('Hola, ', $events[0][1]['text']);
        $done = end($events)[1];
        $this->assertSame('Hola, Salva.', $done['choices'][0]['message']['content']);
        $this->assertSame('none', $done['agenda']['status']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $done['interaction_id']);
        $this->assertTrue($this->requests[0]['stream']);
    }

    public function test_json_clients_keep_the_same_contract(): void
    {
        $this->provider->append(new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hola']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 2]])));

        $this->actingAs($this->owner)->postJson('/generate-text', ['prompt' => 'Hola', 'generateAudio' => false])
            ->assertOk()->assertHeader('Content-Type', 'application/json')->assertJsonPath('choices.0.message.content', 'Hola');
    }

    public function test_streamed_usage_is_recorded(): void
    {
        $this->capture($this->sse(['Hola'], usage: ['input_tokens' => 321, 'output_tokens' => 7]));

        $this->streamChat('Hola');

        $this->assertSame([321, 7], [(int) DB::table('api_usage_logs')->value('prompt_tokens'), (int) DB::table('api_usage_logs')->value('completion_tokens')]);
    }

    public function test_a_saved_agenda_action_is_announced_before_the_text_finishes(): void
    {
        config(['ai.agenda_tools.enabled' => true]);
        $this->capture($this->sse([], [['type' => 'function_call', 'id' => 'fc_1', 'call_id' => 'call_1', 'name' => 'agenda_create_event', 'arguments' => json_encode([
            'title' => 'Llamar a Ana', 'date' => '2026-09-22', 'time' => '10:00', 'end_time' => null, 'all_day' => false, 'location' => null,
            'reminder_minutes_before' => null, 'recurrence' => ['type' => 'none', 'days' => null, 'until' => null]])]]));
        $this->capture(new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Listo.']]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 2]])));

        $events = $this->streamChat('Recuérdame llamar a Ana mañana a las 10');

        $names = array_column($events, 0);
        $this->assertLessThan(array_search('done', $names, true), array_search('agenda', $names, true));
        $this->assertSame('created', $events[array_search('agenda', $names, true)][1]['status']);
        $this->assertSame('Listo.', end($events)[1]['choices'][0]['message']['content']);
        $this->assertSame(1, CalendarEvent::count());
    }

    public function test_a_provider_failure_is_reported_as_an_error_event(): void
    {
        $this->provider->append(new Response(400, [], json_encode(['error' => ['message' => 'bad']])));

        $events = $this->streamChat('Hola');

        $this->assertSame('error', end($events)[0]);
        $this->assertStringNotContainsString('bad', json_encode($events), 'No se filtra el detalle del proveedor.');
    }

    public function test_recoverable_provider_errors_are_retried_once_and_others_are_not(): void
    {
        $ok = new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hola']]]], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]]));
        $this->provider->append(new Response(503, [], '{}'), $ok);
        $this->actingAs($this->owner)->postJson('/generate-text', ['prompt' => 'Hola', 'generateAudio' => false])->assertOk()->assertJsonPath('choices.0.message.content', 'Hola');

        $this->provider->append(new ConnectException('down', new PsrRequest('POST', 'https://api.openai.com/v1/responses')), $ok);
        $this->actingAs($this->owner)->postJson('/generate-text', ['prompt' => 'Hola', 'generateAudio' => false])->assertOk();

        $this->provider->append(new Response(400, [], '{}'), $ok);
        $this->actingAs($this->owner)->postJson('/generate-text', ['prompt' => 'Hola', 'generateAudio' => false]);
        $this->assertSame(1, $this->provider->count(), 'Un 400 no se reintenta: la respuesta buena queda sin usar.');
    }

    public function test_web_search_context_size_is_configurable(): void
    {
        config(['ai.web_search.context_size' => 'low']);
        $this->capture(new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hola']]]], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])));

        $this->actingAs($this->owner)->postJson('/generate-text', ['prompt' => 'Hola', 'generateAudio' => false])->assertOk();

        $this->assertSame('low', collect($this->requests[0]['tools'])->firstWhere('type', 'web_search_preview')['search_context_size']);
    }

    public function test_the_text_chat_view_sends_the_voice_preference_and_never_waits_for_server_audio(): void
    {
        $view = file_get_contents(resource_path('views/home/preguntas.blade.php'));

        $this->assertStringContainsString('generateAudio: false', $view);
        $this->assertStringContainsString("localStorage.getItem('cirilo_voice_enabled')", $view);
        $this->assertStringContainsString("localStorage.getItem('cirilo_voice_enabled')", file_get_contents(resource_path('views/home/conversar.blade.php')));
    }
}
