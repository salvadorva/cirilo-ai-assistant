<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Tests\SecurityTestCase;

/** F2-02/F2-07/F2-08: agenda por herramientas del modelo, con validación y ejecución en el servidor. */
class AgendaToolsTest extends SecurityTestCase
{
    private User $owner;

    /** @var array<int, array> Cuerpos enviados al proveedor, en orden. */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-21 08:00:00', 'America/Guatemala'));
        config(['services.openai.api_key' => 'fake-test-key', 'ai.agenda_tools.enabled' => true]);
        $this->owner = $this->user();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Encola respuestas del proveedor: arrays = llamadas a funciones, string = texto final. */
    private function script(array|string ...$turns): void
    {
        foreach ($turns as $i => $turn) {
            $this->provider->append(function ($request) use ($turn, $i) {
                $this->requests[] = json_decode((string) $request->getBody(), true);
                $output = is_string($turn)
                    ? [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $turn]]]]
                    : array_map(fn ($call, $n) => ['type' => 'function_call', 'id' => "fc_{$i}_{$n}", 'call_id' => "call_{$i}_{$n}",
                        'name' => $call[0], 'arguments' => json_encode($call[1])], $turn, array_keys($turn));

                return new Response(200, [], json_encode(['output' => $output, 'usage' => ['input_tokens' => 100, 'output_tokens' => 10]]));
            });
        }
    }

    private function chat(string $prompt, array $headers = [], ?User $user = null)
    {
        $response = $this->flushHeaders()->actingAs($user ?? $this->owner)->withHeaders($headers)
            ->postJson('/generate-text', ['prompt' => $prompt, 'generateAudio' => false]);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function toolOutputs(int $request): array
    {
        return array_values(array_map(fn ($item) => json_decode($item['output'], true),
            array_filter($this->requests[$request]['input'], fn ($item) => ($item['type'] ?? null) === 'function_call_output')));
    }

    private function event(string $title, string $start, array $extra = []): CalendarEvent
    {
        return CalendarEvent::create($extra + ['user_id' => $this->owner->id, 'title' => $title, 'start_date' => $start,
            'end_date' => Carbon::parse($start)->addMinutes(30), 'status' => 'pending', 'notified' => true]);
    }

    private const CREATE_ANA = ['title' => 'Llamar a Ana', 'date' => '2026-09-22', 'time' => '10:00', 'end_time' => null, 'all_day' => false,
        'location' => null, 'reminder_minutes_before' => null, 'recurrence' => ['type' => 'none', 'days' => null, 'until' => null]];

    public function test_tools_are_offered_and_a_created_event_is_confirmed_only_from_the_tool_result(): void
    {
        $this->script([['agenda_create_event', self::CREATE_ANA]], 'Listo, quedó agendado.');

        $this->chat('Recuérdame llamar a Ana mañana a las 10')->assertOk()
            ->assertJsonPath('agenda.status', 'created')
            ->assertJsonPath('calendar_event_created.title', 'Llamar a Ana')
            ->assertJsonPath('choices.0.message.content', 'Listo, quedó agendado.');

        $this->assertSame('2026-09-22 10:00', CalendarEvent::sole()->start_date->format('Y-m-d H:i'));
        $names = array_column(array_filter($this->requests[0]['tools'], fn ($t) => $t['type'] === 'function'), 'name');
        $this->assertContains('agenda_update_event', $names);
        $this->assertTrue(collect($this->requests[0]['tools'])->where('type', 'function')->every(fn ($t) => $t['strict'] === true));
        $this->assertStringContainsString('usa SIEMPRE las herramientas agenda_', $this->requests[0]['instructions']);
        $this->assertSame('created', $this->toolOutputs(1)[0]['status']);
        // El segundo turno reenvía la llamada del modelo junto con su resultado.
        $this->assertContains('function_call', array_column($this->requests[1]['input'], 'type'));
    }

    public function test_the_deterministic_extractor_is_not_used_when_tools_are_enabled(): void
    {
        Http::fake();
        $this->script('¿A qué hora?');

        $this->chat('Agéndame una llamada mañana')->assertOk()->assertJsonPath('agenda.status', 'needs_input');

        Http::assertNothingSent();
    }

    public function test_missing_time_is_not_invented(): void
    {
        $this->script([['agenda_create_event', ['time' => null] + self::CREATE_ANA]], '¿A qué hora?');

        $this->chat('Agéndame llamar a Ana mañana')->assertJsonPath('agenda.status', 'needs_input')->assertJsonPath('agenda.missing', ['time']);

        $this->assertSame(0, CalendarEvent::count());
    }

    public function test_an_ambiguous_target_asks_for_clarification_and_changes_nothing(): void
    {
        $ana = $this->event('Cita con Ana', '2026-09-21 09:00');
        $luis = $this->event('Cita con Luis', '2026-09-21 10:00');
        $this->script(
            [['agenda_query_events', ['from' => '2026-09-21', 'to' => '2026-09-21', 'text' => null]]],
            [['agenda_request_clarification', ['question' => '¿Cuál de las dos?', 'candidate_event_ids' => [$ana->id, $luis->id]]]],
            '¿Cuál quieres mover: la cita con Ana o con Luis?'
        );

        $this->chat('Muévela a las 11')->assertJsonPath('agenda.status', 'needs_clarification')
            ->assertJsonCount(2, 'agenda.candidates');

        $this->assertSame(['09:00', '10:00'], CalendarEvent::orderBy('id')->get()->map(fn ($e) => $e->start_date->format('H:i'))->all());
        $this->assertCount(2, $this->toolOutputs(1)[0]['events'], 'La consulta devolvió ambos candidatos.');
    }

    public function test_updating_an_event_keeps_its_duration_and_reschedules_the_reminder(): void
    {
        $ana = $this->event('Cita con Ana', '2026-09-21 09:00');
        $this->script([['agenda_update_event', ['event_id' => $ana->id, 'title' => null, 'date' => null, 'time' => '11:00', 'end_time' => null,
            'location' => null, 'apply_to_series' => false, 'series_change_confirmed' => false]]], 'La moví a las 11.');

        $this->chat('Mueve la cita con Ana a las 11')->assertJsonPath('agenda.status', 'updated');

        $ana->refresh();
        $this->assertSame(['11:00', '11:30', false], [$ana->start_date->format('H:i'), $ana->end_date->format('H:i'), $ana->notified]);
    }

    public function test_events_of_other_users_cannot_be_changed(): void
    {
        $foreign = CalendarEvent::create(['user_id' => $this->user()->id, 'title' => 'Ajeno', 'start_date' => '2026-09-21 09:00', 'status' => 'pending']);
        $this->script([['agenda_cancel_event', ['event_id' => $foreign->id, 'apply_to_series' => false, 'series_change_confirmed' => false]]], 'No lo encontré.');

        $this->chat('Cancela el evento '.$foreign->id)->assertJsonPath('agenda.status', 'invalid');

        $this->assertSame('pending', $foreign->fresh()->status);
        $this->assertSame('not_found', $this->toolOutputs(1)[0]['status']);
    }

    public function test_a_whole_series_needs_explicit_confirmation(): void
    {
        foreach (['2026-09-21', '2026-09-22', '2026-09-23'] as $day) {
            $this->event('Gimnasio', "{$day} 18:00", ['series_id' => 'serie-1']);
        }
        $first = CalendarEvent::orderBy('start_date')->first();
        $this->script(
            [['agenda_cancel_event', ['event_id' => $first->id, 'apply_to_series' => true, 'series_change_confirmed' => false]]],
            '¿Seguro que quieres cancelar toda la serie?'
        );

        $this->chat('Cancela el gimnasio')->assertJsonPath('agenda.status', 'needs_confirmation');
        $this->assertSame(0, CalendarEvent::where('status', 'cancelled')->count());

        $this->script([['agenda_cancel_event', ['event_id' => $first->id, 'apply_to_series' => true, 'series_change_confirmed' => true]]], 'Cancelada.');
        $this->chat('Sí, toda la serie')->assertJsonPath('agenda.status', 'cancelled');
        $this->assertSame(3, CalendarEvent::where('status', 'cancelled')->count());
    }

    public function test_the_tool_loop_is_bounded(): void
    {
        $query = [['agenda_query_events', ['from' => '2026-09-21', 'to' => '2026-09-21', 'text' => null]]];
        $this->script($query, $query, $query, $query, $query, $query);

        $response = $this->chat('¿Qué tengo hoy?')->assertOk();

        $this->assertCount((int) config('ai.agenda_tools.max_iterations'), $this->requests);
        $this->assertStringContainsString('No pude completar', $response->json('choices.0.message.content'));
    }

    public function test_a_retried_request_with_the_same_key_does_not_duplicate(): void
    {
        $this->script([['agenda_create_event', self::CREATE_ANA]], 'Listo.', [['agenda_create_event', self::CREATE_ANA]], 'Listo.');

        $this->chat('Recuérdame llamar a Ana mañana a las 10', ['Idempotency-Key' => 'k-1'])->assertJsonPath('agenda.status', 'created');
        $this->chat('Recuérdame llamar a Ana mañana a las 10', ['Idempotency-Key' => 'k-1'])->assertJsonPath('agenda.status', 'replayed');

        $this->assertSame(1, CalendarEvent::count());
    }

    public function test_grok_keeps_the_deterministic_flow_without_tools(): void
    {
        config(['services.grok.api_key' => 'fake-grok']);
        $this->owner->forceFill(['ai_provider' => 'grok'])->save();
        $this->provider->append(function ($request) {
            $this->requests[] = json_decode((string) $request->getBody(), true);

            return new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Hola']]], 'usage' => ['total_tokens' => 10]]));
        });

        $this->chat('Hola, ¿qué tal?')->assertOk();

        $this->assertArrayNotHasKey('tools', $this->requests[0]);
    }

    // ---- Hallazgos de la evaluación real (29/09/2026) ----

    public function test_instructions_require_the_tools_for_missing_data_and_ambiguity(): void
    {
        $this->script('Hola');
        $this->chat('Hola');

        $this->assertStringContainsString('llama agenda_create_event aunque falte la hora', $this->requests[0]['instructions']);
        $this->assertStringContainsString('DEBES llamar agenda_request_clarification', $this->requests[0]['instructions']);
    }

    public function test_a_textual_question_after_an_ambiguous_query_is_reported_as_clarification(): void
    {
        $this->event('Cita con Ana', '2026-09-21 09:00');
        $this->event('Cita con Luis', '2026-09-21 10:00');
        $this->script([['agenda_query_events', ['from' => '2026-09-21', 'to' => '2026-09-21', 'text' => null]]], '¿Cuál quieres mover, la de Ana o la de Luis?');

        $this->chat('Muévela a las 11')->assertJsonPath('agenda.status', 'needs_clarification')->assertJsonCount(2, 'agenda.candidates');

        $this->assertSame(['09:00', '10:00'], CalendarEvent::orderBy('id')->get()->map(fn ($e) => $e->start_date->format('H:i'))->all());
    }

    public function test_a_plain_query_with_a_closing_question_is_not_a_clarification(): void
    {
        $this->event('Cita con Ana', '2026-09-21 09:00');
        $this->event('Cita con Luis', '2026-09-21 10:00');
        $this->script([['agenda_query_events', ['from' => '2026-09-21', 'to' => '2026-09-21', 'text' => null]]], 'Tienes dos citas hoy. ¿Algo más?');

        $this->chat('¿Qué tengo hoy?')->assertJsonPath('agenda.status', 'queried')->assertJsonCount(0, 'agenda.candidates');
    }

    public function test_asking_for_the_time_without_the_tool_is_reported_as_needs_input(): void
    {
        $this->script('¿A qué hora quieres la llamada?');

        $this->chat('Agéndame una llamada con el banco mañana')->assertJsonPath('agenda.status', 'needs_input');

        $this->assertSame(0, CalendarEvent::count());
    }
}
