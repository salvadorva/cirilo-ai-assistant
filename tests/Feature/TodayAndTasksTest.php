<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Task;
use App\Models\User;
use App\Services\ConversationSummaryService;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Tests\SecurityTestCase;

/** F6: vista «Hoy», pendientes con estado y resumen diario opcional. */
class TodayAndTasksTest extends SecurityTestCase
{
    private User $owner;

    private array $instructions = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['2025_09_04_110118_create_notifications_table.php', '2026_03_30_103641_add_telegram_fields_to_users_table.php'] as $file) {
            $this->artisan('migrate', ['--path' => 'database/migrations/'.$file, '--force' => true])->assertExitCode(0);
        }
        Carbon::setTestNow(Carbon::parse('2026-09-21 08:00:00', 'America/Guatemala')); // lunes
        config(['services.openai.api_key' => 'fake-test-key']);
        $this->owner = $this->user();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reply(): void
    {
        $this->provider->append(function ($request) {
            $this->instructions[] = json_decode((string) $request->getBody(), true)['instructions'] ?? '';

            return new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Listo']]]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 2]]));
        });
    }

    private function chat(string $prompt)
    {
        $this->reply();
        $response = $this->flushHeaders()->actingAs($this->owner)->postJson('/generate-text', ['prompt' => $prompt, 'generateAudio' => false]);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function mobile(string $method, string $uri, array $data = [], ?User $user = null)
    {
        $this->app['auth']->forgetGuards(); // sin la sesión web de un actingAs anterior: solo el token
        $response = $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.($user ?? $this->owner)->createToken('android')->plainTextToken])->json($method, $uri, $data);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function event(string $title, string $start): CalendarEvent
    {
        return CalendarEvent::create(['user_id' => $this->owner->id, 'title' => $title, 'start_date' => $start, 'end_date' => Carbon::parse($start)->addHour(),
            'status' => 'pending', 'notified' => false]);
    }

    private function task(string $title, array $extra = []): Task
    {
        return Task::create($extra + ['user_id' => $this->owner->id, 'title' => $title, 'status' => 'open', 'source' => 'web']);
    }

    public function test_an_undated_pending_item_is_saved_without_inventing_a_schedule(): void
    {
        $this->chat('Tengo que revisar la propuesta, todavía sin fecha')->assertOk()
            ->assertJsonPath('task_created.title', 'Revisar la propuesta')
            ->assertJsonPath('tasks.status', 'created');

        $task = Task::sole();
        $this->assertSame(['open', null, 'chat'], [$task->status, $task->due_date, $task->source]);
        $this->assertSame(0, CalendarEvent::count());
        $this->assertStringContainsString('pendiente sin fecha', end($this->instructions));
    }

    public function test_capture_phrases_and_non_task_messages(): void
    {
        $this->chat('Guarda como pendiente sin fecha: revisar el contrato')->assertJsonPath('task_created.title', 'Revisar el contrato');
        $this->chat('Anota como pendiente: comprar tinta')->assertJsonPath('task_created.title', 'Comprar tinta');
        $this->chat('Tengo hambre')->assertJsonPath('tasks.status', 'none');
        $this->chat('Tengo que revisar la propuesta, todavía sin fecha');
        $this->chat('Tengo que revisar la propuesta, todavía sin fecha')->assertJsonPath('tasks.status', 'duplicate');

        $this->assertSame(['Revisar el contrato', 'Comprar tinta', 'Revisar la propuesta'], Task::orderBy('id')->pluck('title')->all());
    }

    public function test_lifecycle_complete_postpone_dismiss_and_reopen_with_ownership(): void
    {
        $task = $this->task('Pagar la luz');
        $this->actingAs($this->owner);

        $this->patchJson("/pendientes/{$task->id}", ['action' => 'postpone', 'until' => '2026-09-23'])->assertOk()->assertJsonPath('task.status', 'postponed');
        $this->patchJson("/pendientes/{$task->id}", ['action' => 'postpone', 'until' => '2026-09-20'])->assertStatus(422);
        $this->patchJson("/pendientes/{$task->id}", ['action' => 'complete'])->assertOk()->assertJsonPath('task.status', 'done');
        $this->assertNotNull($task->fresh()->completed_at);
        $this->patchJson("/pendientes/{$task->id}", ['action' => 'reopen'])->assertJsonPath('task.status', 'open');
        $this->patchJson("/pendientes/{$task->id}", ['action' => 'dismiss'])->assertJsonPath('task.status', 'dismissed');
        $this->patchJson("/pendientes/{$task->id}", ['title' => 'Pagar la luz y el agua', 'due_date' => '2026-09-25'])->assertJsonPath('task.title', 'Pagar la luz y el agua');

        $this->mobile('PATCH', "/api/mobile/tasks/{$task->id}", ['action' => 'complete'], $this->user())->assertNotFound();
        $this->mobile('PATCH', "/api/mobile/tasks/{$task->id}", ['action' => 'complete'])->assertOk()->assertJsonPath('task.status', 'done');
    }

    public function test_today_and_the_chat_show_the_same_commitments(): void
    {
        $this->event('Dentista', '2026-09-21 10:00');
        $this->event('Mañana no', '2026-09-22 10:00');
        $this->task('Sin fecha');
        $this->task('Vence hoy', ['due_date' => '2026-09-21']);
        $this->task('Vencido', ['due_date' => '2026-09-19']);
        $this->task('Para el viernes', ['due_date' => '2026-09-25']);
        $this->task('Pospuesto a hoy', ['status' => 'postponed', 'postponed_until' => '2026-09-21']);
        $this->task('Pospuesto a mañana', ['status' => 'postponed', 'postponed_until' => '2026-09-22']);
        $this->task('Hecho', ['status' => 'done']);
        $this->task('Sugerido', ['status' => 'suggested', 'source' => 'summary']);

        $today = $this->actingAs($this->owner)->getJson('/hoy/datos')->assertOk();

        $this->assertSame(['Dentista'], array_column($today->json('events'), 'title'));
        $this->assertEqualsCanonicalizing(['Sin fecha', 'Vence hoy', 'Vencido', 'Pospuesto a hoy'], array_column($today->json('tasks'), 'title'));
        $this->assertSame(['Sugerido'], array_column($today->json('suggestions'), 'title'));
        $this->assertStringContainsString('1 compromiso', $today->json('summary'));
        $this->assertStringStartsWith('/agenda?fecha=2026-09-21&evento=', $today->json('events.0.url'));

        $this->chat('¿Qué tengo hoy?');
        $context = end($this->instructions);
        $this->assertStringContainsString('Dentista', $context);
        $this->assertStringNotContainsString('Mañana no', $context);
        $this->assertStringContainsString('Vence hoy', $context);
        $this->assertStringNotContainsString('Hecho', $context, 'Lo completado no reaparece en el chat.');

        $this->mobile('GET', '/api/mobile/today')->assertOk()->assertJsonPath('events.0.title', 'Dentista');
        $this->actingAs($this->owner)->get('/hoy')->assertOk()->assertSee('Dentista')->assertSee('Vence hoy');
    }

    public function test_summary_pending_items_become_suggestions_not_commitments(): void
    {
        $conversation = Conversation::create(['user_id' => $this->owner->id, 'title' => 'T', 'type' => 'chat', 'content' => '{}']);
        $summary = fn (array $items) => new Response(200, [], json_encode(['choices' => [['message' => ['content' => json_encode(['summary' => 'R', 'pending_items' => $items, 'extracted_facts' => []])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 2]]));
        $this->provider->append($summary(['Enviar la cotización', 'Llamar al banco']));

        app(ConversationSummaryService::class)->maybeSummarize($conversation, array_fill(0, 4, ['role' => 'user', 'content' => 'x']), true);

        $this->assertSame(['suggested', 'suggested'], Task::orderBy('id')->pluck('status')->all());
        $this->assertSame($conversation->id, Task::first()->source_conversation_id);
        $this->actingAs($this->owner)->patchJson('/pendientes/'.Task::first()->id, ['action' => 'accept'])->assertJsonPath('task.status', 'open');
        $this->patchJson('/pendientes/'.Task::orderByDesc('id')->first()->id, ['action' => 'dismiss']);

        // Un resumen posterior con los mismos puntos no los vuelve a proponer.
        $this->provider->append($summary(['Enviar la cotización', 'Llamar al banco']));
        app(ConversationSummaryService::class)->maybeSummarize($conversation->fresh(), array_fill(0, 8, ['role' => 'user', 'content' => 'x']), true);
        $this->assertSame(2, Task::count());
    }

    public function test_tasks_via_model_tools(): void
    {
        config(['ai.agenda_tools.enabled' => true]);
        $existing = $this->task('Pagar la luz');
        $call = fn (string $name, array $args) => new Response(200, [], json_encode(['output' => [['type' => 'function_call', 'id' => 'fc', 'call_id' => 'c1',
            'name' => $name, 'arguments' => json_encode($args)]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 2]]));
        $text = new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Listo']]]], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]]));

        $this->provider->append($call('task_create', ['title' => 'Revisar la propuesta', 'due_date' => null, 'notes' => null]), $text);
        $this->actingAs($this->owner)->postJson('/generate-text', ['prompt' => 'Anota revisar la propuesta', 'generateAudio' => false])
            ->assertJsonPath('tasks.status', 'created')->assertJsonPath('task_created.title', 'Revisar la propuesta');

        $this->provider->append($call('task_update', ['task_id' => $existing->id, 'action' => 'complete', 'until' => null]), $text);
        $this->postJson('/generate-text', ['prompt' => 'Ya pagué la luz', 'generateAudio' => false])->assertJsonPath('tasks.status', 'updated');

        $this->assertSame('done', $existing->fresh()->status);
        $this->assertSame(0, CalendarEvent::count());
    }

    public function test_the_daily_summary_is_optional_sent_once_and_respects_days(): void
    {
        $this->event('Dentista', '2026-09-21 10:00');
        $this->task('Pagar la luz');
        $this->task('Ya hecho', ['status' => 'done']);

        $this->artisan('today:send-summary')->assertExitCode(0);
        $this->assertSame(0, Notification::count(), 'Desactivado por defecto.');

        $this->mobile('GET', '/api/mobile/today')->assertJsonPath('preferences.enabled', false)->assertJsonPath('preferences.time', '07:30');
        $this->actingAs($this->owner)->putJson('/hoy/preferencias', ['enabled' => true, 'time' => '08:00', 'channel' => 'internal', 'days' => 'weekdays'])->assertOk();
        $this->mobile('GET', '/api/mobile/today')->assertJsonPath('preferences', ['enabled' => true, 'time' => '08:00', 'channel' => 'internal', 'days' => 'weekdays']);
        $this->artisan('today:send-summary')->assertExitCode(0);
        $this->artisan('today:send-summary')->assertExitCode(0);

        $notice = Notification::sole();
        $this->assertStringContainsString('Dentista', $notice->message);
        $this->assertStringContainsString('Pagar la luz', $notice->message);
        $this->assertStringNotContainsString('Ya hecho', $notice->message);

        Carbon::setTestNow(Carbon::parse('2026-09-26 08:00:00', 'America/Guatemala')); // sábado
        $this->artisan('today:send-summary')->assertExitCode(0);
        $this->assertSame(1, Notification::count());
    }

    public function test_a_failed_telegram_summary_is_retried_within_the_window(): void
    {
        config(['services.n8n.telegram_webhook' => 'https://telegram.example.test/send']);
        $this->owner->forceFill(['telegram_chat_id' => '1', 'telegram_notifications_enabled' => true, 'daily_summary_enabled' => true,
            'daily_summary_time' => '08:00', 'daily_summary_channel' => 'telegram', 'daily_summary_days' => 'daily'])->save();
        Http::fake(['telegram.example.test/*' => Http::sequence()->push([], 503)->push(['ok' => true])]);

        $this->artisan('today:send-summary')->assertExitCode(0);
        $this->assertNull($this->owner->fresh()->daily_summary_last_sent_on);
        Carbon::setTestNow(now()->addMinute());
        $this->artisan('today:send-summary')->assertExitCode(0);

        $this->assertSame('2026-09-21', $this->owner->fresh()->daily_summary_last_sent_on?->format('Y-m-d'));
    }
}
