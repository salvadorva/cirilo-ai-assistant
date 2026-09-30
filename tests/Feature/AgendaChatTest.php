<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Conversation;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\SecurityTestCase;

/** F2: la agenda por chat solo confirma lo que quedó guardado y pide lo que realmente falta. */
class AgendaChatTest extends SecurityTestCase
{
    private User $owner;

    /** @var array<int, array> Instrucciones enviadas al modelo de chat en cada turno. */
    private array $instructions = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-21 08:00:00', 'America/Guatemala'));
        config(['services.openai.api_key' => 'fake-test-key']);
        $this->owner = $this->user();
    }

    protected function tearDown(): void
    {
        CalendarEvent::flushEventListeners();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function extractor(array ...$payloads): void
    {
        $sequence = Http::sequence();
        foreach ($payloads as $payload) {
            $sequence->push(['choices' => [['message' => ['content' => json_encode($payload)]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]]);
        }
        Http::fake(['api.openai.com/v1/chat/completions' => $sequence]);
    }

    private function extractorCalls(): int
    {
        return count(Http::recorded(fn (HttpRequest $request) => str_contains($request->url(), '/chat/completions')));
    }

    private function reply(int $times = 1): void
    {
        foreach (range(1, $times) as $i) {
            $this->provider->append(function ($request) {
                $this->instructions[] = json_decode((string) $request->getBody(), true)['instructions'] ?? '';

                return new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Respuesta']]]],
                    'usage' => ['input_tokens' => 100, 'output_tokens' => 5]]));
            });
        }
    }

    private function chat(string $prompt, array $extra = [], array $headers = [])
    {
        $response = $this->flushHeaders()->actingAs($this->owner)->withHeaders($headers)
            ->postJson('/generate-text', ['prompt' => $prompt, 'generateAudio' => false] + $extra);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function lastInstructions(): string
    {
        return (string) end($this->instructions);
    }

    private const CALL_ANA = ['title' => 'Llamar a Ana', 'start_date' => '2026-09-22', 'start_time' => '10:00', 'end_time' => null, 'all_day' => false];

    public function test_reminder_phrase_creates_exactly_one_event_at_the_requested_time(): void
    {
        $this->extractor(self::CALL_ANA);
        $this->reply();

        $response = $this->chat('Recuérdame llamar a Ana mañana a las 10')->assertOk()
            ->assertJsonPath('agenda.status', 'created')
            ->assertJsonPath('agenda.events.0.title', 'Llamar a Ana')
            ->assertJsonPath('calendar_event_created.title', 'Llamar a Ana');

        $event = CalendarEvent::sole();
        $this->assertSame('2026-09-22 10:00', $event->start_date->format('Y-m-d H:i'));
        $this->assertSame('2026-09-22 11:00', $event->end_date->format('Y-m-d H:i'));
        $this->assertSame($event->id, $response->json('agenda.events.0.id'));
        $this->assertStringContainsString('Evento(s) creado(s)', $this->lastInstructions());
    }

    public function test_negations_and_programming_requests_do_not_touch_the_agenda(): void
    {
        $this->extractor(self::CALL_ANA);
        foreach (['No quiero agendar una reunión mañana', 'Puedes crear una función en PHP', '¿Puedes programar un script en Python?', 'No me agendes nada hoy'] as $prompt) {
            $this->reply();
            $this->chat($prompt)->assertOk()->assertJsonPath('agenda.status', 'none');
        }

        $this->assertSame(0, $this->extractorCalls());
        $this->assertSame(0, CalendarEvent::count());
    }

    public function test_missing_time_is_asked_and_never_defaults_to_nine(): void
    {
        $conversation = Conversation::create(['user_id' => $this->owner->id, 'title' => 'Agenda', 'type' => 'chat', 'content' => '{}']);
        $this->extractor(
            ['title' => 'Llamada', 'start_date' => '2026-09-22', 'start_time' => null, 'all_day' => false],
            ['title' => 'Llamada', 'start_date' => '2026-09-22', 'start_time' => '10:00', 'all_day' => false],
        );
        $this->reply(2);

        $this->chat('Agéndame una llamada mañana', ['conversation_id' => $conversation->id])->assertOk()
            ->assertJsonPath('agenda.status', 'needs_input')->assertJsonPath('agenda.missing', ['start_time']);
        $this->assertSame(0, CalendarEvent::count());
        $this->assertStringContainsString('hora', $this->lastInstructions());
        $this->assertStringNotContainsString('Evento(s) creado(s)', $this->lastInstructions());

        // Siguiente turno de la misma conversación: completa el borrador.
        $this->chat('a las 10', ['conversation_id' => $conversation->id])->assertOk()->assertJsonPath('agenda.status', 'created');
        $this->assertSame('2026-09-22 10:00', CalendarEvent::sole()->start_date->format('Y-m-d H:i'));
    }

    public function test_all_day_only_when_the_user_says_so(): void
    {
        $this->extractor(['title' => 'Cumpleaños de Luis', 'start_date' => '2026-09-25', 'start_time' => null, 'all_day' => true]);
        $this->reply();

        $this->chat('Agenda el cumpleaños de Luis el viernes, todo el día')->assertOk()->assertJsonPath('agenda.status', 'created');

        $this->assertTrue(CalendarEvent::sole()->all_day);
    }

    public function test_drafts_belong_to_their_conversation(): void
    {
        $first = Conversation::create(['user_id' => $this->owner->id, 'title' => 'A', 'type' => 'chat', 'content' => '{}']);
        $second = Conversation::create(['user_id' => $this->owner->id, 'title' => 'B', 'type' => 'chat', 'content' => '{}']);
        $this->extractor(['title' => 'Llamada', 'start_date' => '2026-09-22', 'start_time' => null], self::CALL_ANA);
        $this->reply(2);

        $this->chat('Agéndame una llamada mañana', ['conversation_id' => $first->id])->assertJsonPath('agenda.status', 'needs_input');
        $this->chat('a las 10', ['conversation_id' => $second->id])->assertJsonPath('agenda.status', 'none');

        $this->assertSame(1, $this->extractorCalls());
        $this->assertSame(0, CalendarEvent::count());
    }

    public function test_the_user_can_abandon_a_draft(): void
    {
        $conversation = Conversation::create(['user_id' => $this->owner->id, 'title' => 'A', 'type' => 'chat', 'content' => '{}']);
        $this->extractor(['title' => 'Llamada', 'start_date' => '2026-09-22', 'start_time' => null], self::CALL_ANA);
        $this->reply(3);

        $this->chat('Agéndame una llamada mañana', ['conversation_id' => $conversation->id])->assertJsonPath('agenda.status', 'needs_input');
        $this->chat('Olvídalo, mejor no', ['conversation_id' => $conversation->id])->assertJsonPath('agenda.status', 'draft_cancelled');
        $this->chat('a las 10', ['conversation_id' => $conversation->id])->assertJsonPath('agenda.status', 'none');

        $this->assertSame(1, $this->extractorCalls());
        $this->assertSame(0, CalendarEvent::count());
    }

    public function test_a_draft_expires(): void
    {
        $conversation = Conversation::create(['user_id' => $this->owner->id, 'title' => 'A', 'type' => 'chat', 'content' => '{}']);
        $this->extractor(['title' => 'Llamada', 'start_date' => '2026-09-22', 'start_time' => null], self::CALL_ANA);
        $this->reply(2);

        $this->chat('Agéndame una llamada mañana', ['conversation_id' => $conversation->id])->assertJsonPath('agenda.status', 'needs_input');
        Carbon::setTestNow(now()->addHour());
        $this->chat('a las 10', ['conversation_id' => $conversation->id])->assertJsonPath('agenda.status', 'none');

        $this->assertSame(0, CalendarEvent::count());
    }

    public function test_a_retry_with_the_same_idempotency_key_returns_the_previous_result(): void
    {
        $this->extractor(self::CALL_ANA, self::CALL_ANA);
        $this->reply(3);

        $first = $this->chat('Recuérdame llamar a Ana mañana a las 10', [], ['Idempotency-Key' => 'op-123'])->assertJsonPath('agenda.status', 'created');
        $second = $this->chat('Recuérdame llamar a Ana mañana a las 10', [], ['Idempotency-Key' => 'op-123'])->assertJsonPath('agenda.status', 'replayed');

        $this->assertSame($first->json('agenda.events.0.id'), $second->json('agenda.events.0.id'));
        $this->assertSame(1, CalendarEvent::count());
        $this->assertSame(1, $this->extractorCalls(), 'La repetición no vuelve a extraer.');

        // Misma clave con otra petición: no se ejecuta.
        $this->chat('Recuérdame comprar pan mañana a las 9', [], ['Idempotency-Key' => 'op-123'])->assertJsonPath('agenda.status', 'conflict');
        $this->assertSame(1, CalendarEvent::count());
    }

    public function test_the_same_event_resent_without_a_key_is_reported_as_duplicate(): void
    {
        $this->extractor(self::CALL_ANA, self::CALL_ANA);
        $this->reply(2);

        $this->chat('Recuérdame llamar a Ana mañana a las 10')->assertJsonPath('agenda.status', 'created');
        $this->chat('Recuérdame llamar a Ana mañana a las 10')->assertJsonPath('agenda.status', 'duplicate');

        $this->assertSame(1, CalendarEvent::count());
        $this->assertStringContainsString('ya estaba', $this->lastInstructions());
    }

    public function test_a_persistence_failure_is_reported_and_never_confirmed(): void
    {
        $this->extractor(self::CALL_ANA);
        $this->reply();
        CalendarEvent::creating(fn () => throw new \RuntimeException('Simulated database outage'));

        $response = $this->chat('Recuérdame llamar a Ana mañana a las 10')->assertOk()->assertJsonPath('agenda.status', 'failed');

        $this->assertNull($response->json('calendar_event_created'));
        $this->assertSame(0, CalendarEvent::count());
        $this->assertStringContainsString('NO se pudo guardar', $this->lastInstructions());
    }

    public function test_a_series_is_created_atomically(): void
    {
        $this->extractor(['title' => 'Llamada diaria', 'start_date' => '2026-09-21', 'start_time' => '10:00', 'recurrence_type' => 'daily', 'recurrence_end_date' => '2026-09-23']);
        $this->reply();
        $n = 0;
        CalendarEvent::creating(function () use (&$n) {
            if (++$n === 2) {
                throw new \RuntimeException('Simulated second INSERT failure');
            }
        });

        $this->chat('Agenda una llamada diaria a las 10 hasta el 23')->assertJsonPath('agenda.status', 'failed');

        $this->assertSame(0, CalendarEvent::count());
    }

    public function test_mobile_chat_returns_event_created_and_the_agenda_contract(): void
    {
        $this->extractor(self::CALL_ANA);
        $this->reply();

        $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$this->owner->createToken('android')->plainTextToken])
            ->postJson('/api/mobile/chat', ['prompt' => 'Recuérdame llamar a Ana mañana a las 10', 'generateAudio' => false])->assertOk()
            ->assertJsonPath('event_created.title', 'Llamar a Ana')
            ->assertJsonPath('agenda.status', 'created');
    }
}
