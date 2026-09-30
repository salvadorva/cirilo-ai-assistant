<?php

namespace Tests\Feature;

use App\Console\Commands\ScheduleEventNotifications;
use App\Console\Commands\SendFocusMessages;
use App\Http\Controllers\AIController;
use App\Jobs\SendEventStartedJob;
use App\Models\CalendarEvent;
use App\Models\Conversation;
use App\Models\FocusSlot;
use App\Models\Notification;
use App\Models\UserProfileFact;
use App\Services\AiPricing;
use App\Services\ConversationSummaryService;
use App\Services\FcmService;
use App\Services\MemoryService;
use App\Services\TtsService;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\SecurityTestCase;

/** Characterization, not an assertion that all product acceptance criteria pass. */
class CiriloBaselineTest extends SecurityTestCase
{
    public static function cases(): array
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/cirilo-baseline.json'), true, flags: JSON_THROW_ON_ERROR);

        return array_column(array_map(fn ($case) => [$case['id'], [$case]], $fixture['cases']), 1, 0);
    }

    #[DataProvider('cases')]
    public function test_controlled_baseline(array $case): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('America/Guatemala');
        Carbon::setTestNow(Carbon::parse('2026-09-21 08:00:00', 'America/Guatemala'));
        try {
            $start = hrtime(true);
            $observed = $this->probe($case);
            $this->assertSame($case['baseline']['observed'], $observed, $case['id'].' changed: review the baseline, do not blindly update it.');
            if (getenv('CIRILO_BASELINE_REPORT') === '1') {
                fwrite(STDOUT, json_encode(['id' => $case['id'], 'status' => $case['baseline']['status'], 'observed' => $observed,
                    'harness_ms' => round((hrtime(true) - $start) / 1e6, 2), 'provider_latency_ms' => null, 'real_cost_usd' => null], JSON_UNESCAPED_UNICODE).PHP_EOL);
            }
        } finally {
            CalendarEvent::flushEventListeners();
            Carbon::setTestNow();
            date_default_timezone_set($timezone);
        }
    }

    private function invoke(string $method, ...$args): mixed
    {
        return (new \ReflectionMethod(AIController::class, $method))->invoke(app(AIController::class), ...$args);
    }

    private function text(): Response
    {
        return new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Texto ficticio de referencia.']]]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20]]));
    }

    private function event(int $userId, string $title, string $start): CalendarEvent
    {
        return CalendarEvent::create(['user_id' => $userId, 'title' => $title, 'start_date' => $start, 'end_date' => Carbon::parse($start)->addHour(), 'reminder_minutes_before' => 30, 'status' => 'pending', 'notified' => false]);
    }

    private function conversation(int $userId, array $values = []): Conversation
    {
        return Conversation::create($values + ['user_id' => $userId, 'title' => 'Ficticia', 'content' => '{}', 'type' => 'chat']);
    }

    private function extraction(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{"title":"Llamada","start_date":"2026-09-22","start_time":"10:00","end_time":"11:00"}']]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]])]);
    }

    private function summaryResponse(string $summary = 'Resumen antiguo'): void
    {
        config(['services.openai.api_key' => 'fake-test-key']);
        $this->provider->append(new Response(200, [], json_encode(['choices' => [['message' => ['content' => json_encode(['summary' => $summary, 'extracted_facts' => []])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]])));
    }

    private function notificationTables(): void
    {
        foreach (['2025_09_04_110118_create_notifications_table.php', '2026_03_30_103641_add_telegram_fields_to_users_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->mock(FcmService::class, fn ($mock) => $mock->shouldReceive('sendToUser')->andReturn(false));
        app(Kernel::class)->registerCommand(new ScheduleEventNotifications(app(FcmService::class)));
    }

    private function probe(array $case): mixed
    {
        $probe = $case['probe'];
        if ($probe === 'browser') {
            $this->assertFileExists(base_path('tests/frontend/safe-content.test.mjs'));

            return 'external_suite'; // Must run npm run test:security separately.
        }
        if ($probe === 'anonymous') {
            return [$this->postJson('/generate-text', ['prompt' => 'Hola'])->status(), $this->postJson('/text-to-speech', ['text' => 'Hola'])->status()];
        }
        if ($probe === 'intent') {
            return $this->invoke('detectCalendarCreateIntent', $case['input']);
        }
        if ($probe === 'missing_time') {
            return $this->invoke('isEventDataComplete', ['title' => 'Llamada', 'start_date' => '2026-09-22', 'start_time' => null]);
        }
        if ($probe === 'pricing') {
            return array_map(fn ($model) => app(AiPricing::class)->estimate('openai', $model, ['input_tokens' => 1000, 'output_tokens' => 200])['estimated_cost'], ['gpt-4.1', 'future-model']);
        }
        $user = $this->user();
        $this->actingAs($user);
        if ($probe === 'ownership') {
            $conversation = $this->conversation($this->user()->id);

            return $this->postJson('/generate-text', ['prompt' => 'Hola', 'conversation_id' => $conversation->id])->status();
        }
        if ($probe === 'rate') {
            config(['ai_security.requests_per_minute.chat' => 1]);
            $this->postJson('/generate-text', ['prompt' => 'Qué hora es', 'generateAudio' => false])->assertOk();

            return $this->postJson('/generate-text', ['prompt' => 'Qué hora es', 'generateAudio' => false])->status();
        }
        if (in_array($probe, ['next_week', 'morning'])) {
            $this->event($user->id, 'CURRENT_WEEK TODAY', '2026-09-21 10:00');
            $this->event($user->id, 'TOMORROW', '2026-09-22 10:00');
            $this->event($user->id, 'NEXT_WEEK', '2026-09-28 10:00');
            $context = $this->invoke('getCalendarContext', $user, $case['input']);

            return $probe === 'next_week' ? (str_contains($context, 'NEXT_WEEK') ? 'NEXT_WEEK' : 'CURRENT_WEEK') : (str_contains($context, 'TODAY') ? 'TODAY' : 'TOMORROW');
        }
        if ($probe === 'ambiguity') {
            // Structural server check only; the model's wording is judged in F7 with a real provider.
            $this->event($user->id, 'Cita con Ana', '2026-09-21 09:00');
            $this->event($user->id, 'Cita con Luis', '2026-09-21 10:00');
            $this->provider->append($this->text());
            $result = $this->postJson('/generate-text', ['prompt' => $case['input'], 'generateAudio' => false])->assertOk();

            return [CalendarEvent::orderBy('id')->get()->map(fn ($e) => Carbon::parse($e->start_date)->format('H:i'))->all(),
                $result->json('pending_action') !== null || $result->json('clarification') !== null];
        }
        if ($probe === 'today') {
            // Structural server check: is there any persistent task contract for an undated item?
            $this->provider->append($this->text());
            $result = $this->postJson('/generate-text', ['prompt' => 'Guarda como pendiente sin fecha: revisar el contrato', 'generateAudio' => false])->assertOk();

            return [CalendarEvent::count(), $result->json('task_created') !== null, class_exists('App\\Models\\Task') || class_exists('App\\Models\\PendingItem')];
        }
        if ($probe === 'duplicate') {
            $data = ['title' => 'Misma operación', 'start_date' => '2026-09-22', 'start_time' => '10:00'];
            $this->invoke('createEventFromChat', $data, $user->id);
            $this->invoke('createEventFromChat', $data, $user->id);

            return CalendarEvent::count(); // Sequential diagnostic; not a concurrency proof.
        }
        if ($probe === 'db_failure') {
            $this->extraction();
            CalendarEvent::creating(fn () => throw new \RuntimeException('Simulated database outage'));
            $result = $this->postJson('/generate-text', ['prompt' => $case['input'], 'generateAudio' => false]);
            $this->assertEmpty($result->json('calendar_event_created'));

            return [$result->status(), CalendarEvent::count()];
        }
        if ($probe === 'atomic_series') {
            $n = 0;
            CalendarEvent::creating(function () use (&$n) {
                if (++$n === 2) {
                    throw new \RuntimeException('Simulated second INSERT failure');
                }
            });
            try {
                $this->invoke('createEventFromChat', ['title' => 'Serie', 'start_date' => '2026-09-21', 'recurrence_type' => 'daily', 'recurrence_end_date' => '2026-09-23'], $user->id);
            } catch (\RuntimeException $e) {
                $this->assertSame('Simulated second INSERT failure', $e->getMessage());
            }

            return CalendarEvent::count();
        }
        if ($probe === 'mobile_event') {
            $this->extraction();
            $this->provider->append($this->text());
            $result = $this->postJson('/api/mobile/chat', ['prompt' => $case['input'], 'generateAudio' => false])->assertOk();
            $this->assertDatabaseCount('calendar_events', 1);

            return ! empty($result->json('event_created'));
        }
        if (in_array($probe, ['two_moments', 'telegram_retry', 'catchup'])) {
            $this->notificationTables();
            config(['services.n8n.telegram_webhook' => 'https://telegram.example.test/send', 'services.n8n.webhook_secret' => 'fake']);
            $user->forceFill(['telegram_chat_id' => 'fake', 'telegram_notifications_enabled' => true])->save();
            Http::fake(['telegram.example.test/*' => Http::response([], 503)]);
            if ($probe === 'catchup') {
                $late = $this->event($user->id, 'Vencido', '2026-09-21 08:20');
                $future = $this->event($user->id, 'Futuro', '2026-09-21 08:35');
                $this->artisan('agenda:schedule-notifications')->assertExitCode(0);

                return [$late->fresh()->notified, $future->fresh()->notified];
            }
            $this->event($user->id, 'Llamada', '2026-09-21 08:30');
            $this->artisan('agenda:schedule-notifications')->assertExitCode(0);
            if ($probe === 'two_moments') {
                Carbon::setTestNow(Carbon::parse('2026-09-21 08:30', 'America/Guatemala'));
            }
            $this->artisan('agenda:schedule-notifications')->assertExitCode(0);

            return $probe === 'two_moments' ? Notification::count() : [Http::recorded()->count(), Notification::count()];
        }
        if ($probe === 'queued_stale') {
            (require database_path('migrations/2025_10_15_131954_add_email_notifications_to_users_table.php'))->up();
            $user->forceFill(['email_notifications_enabled' => true])->save();
            $cancelled = $this->event($user->id, 'Cancelada', '2026-09-21 08:00');
            $job = new SendEventStartedJob($cancelled);
            $cancelled->update(['status' => 'cancelled']);
            $sent = 0;
            Mail::shouldReceive('send')->andReturnUsing(function () use (&$sent) {
                $sent++;
            });
            $job->handle();
            $before = $sent;
            $moved = $this->event($user->id, 'Movida', '2026-09-21 08:00');
            $job = new SendEventStartedJob($moved);
            $moved->update(['start_date' => '2026-09-28 08:00']);
            $job->handle();

            return [$before, $sent - $before];
        }
        if ($probe === 'focus_failure') {
            foreach (['2026_07_02_100000_create_focus_slots_table.php', '2026_07_03_100000_add_with_audio_to_focus_slots_table.php', '2026_07_07_100000_add_audio_path_to_focus_slots_table.php'] as $file) {
                (require database_path('migrations/'.$file))->up();
            }
            $this->mock(FcmService::class, fn ($mock) => $mock->shouldReceive('sendToUser')->once()->andReturn(false));
            app(Kernel::class)->registerCommand(new SendFocusMessages(app(FcmService::class), app(TtsService::class)));
            $slot = FocusSlot::create(['user_id' => $user->id, 'time' => '08:00', 'days' => [1], 'title' => 'Enfoque', 'message' => 'Ficticio', 'enabled' => true, 'with_audio' => false]);
            $this->artisan('focus:send-messages')->assertExitCode(0);

            return $slot->fresh()->last_sent_at !== null;
        }
        if ($probe === 'active_memory') {
            $history = array_merge([['role' => 'user', 'content' => 'AGREEMENT_MARKER']], array_fill(0, 10, ['role' => 'user', 'content' => 'Otro tema']));
            $conv = $this->conversation($user->id, ['summary' => 'AGREEMENT_MARKER']);
            $body = '';
            $this->provider->append(function ($request) use (&$body) {
                $body = (string) $request->getBody();

                return $this->text();
            });
            $this->postJson('/generate-text', ['prompt' => $case['input'], 'history' => $history, 'conversation_id' => $conv->id, 'generateAudio' => false])->assertOk();

            return str_contains($body, 'AGREEMENT_MARKER');
        }
        if ($probe === 'old_memory') {
            // F4-04: el acuerdo queda en `decisions` (así lo guarda el resumen) y el chat pasa la pregunta real.
            for ($i = 4; $i >= 1; $i--) {
                $this->conversation($user->id, $i === 4 ? ['summary' => 'OLD_AGREEMENT_MARKER', 'decisions' => ['Acuerdo registrado']] : ['summary' => 'Otro tema'])
                    ->forceFill(['updated_at' => now()->subDays($i)])->save();
            }

            return str_contains(MemoryService::buildContextBlock($user->id, null, $case['input']), 'OLD_AGREEMENT_MARKER');
        }
        if ($probe === 'cross_client') {
            $conv = $this->conversation($user->id);
            $this->putJson('/conversaciones/'.$conv->id, ['content' => json_encode(['messages' => [['role' => 'user', 'content' => 'NEW_WEB_MESSAGE']]])])->assertOk();

            return $conv->messages()->where('content', 'NEW_WEB_MESSAGE')->exists(); // Same source read by mobile API.
        }
        if ($probe === 'brief_memory') {
            // F4-08: el proveedor devuelve el hecho; antes el umbral de 4 mensajes impedía siquiera llamarlo.
            config(['services.openai.api_key' => 'fake-test-key']);
            $this->provider->append(new Response(200, [], json_encode(['choices' => [['message' => ['content' => json_encode(['summary' => 'Preferencia',
                'extracted_facts' => [['category' => 'preferences', 'key' => 'meeting_time', 'value' => 'Después de las 10', 'confidence' => 0.9]]])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]])));
            app(ConversationSummaryService::class)->maybeSummarize($this->conversation($user->id), [['role' => 'user', 'content' => $case['input']], ['role' => 'assistant', 'content' => 'De acuerdo']]);

            return UserProfileFact::where('user_id', $user->id)->exists();
        }
        if ($probe === 'forgotten_memory') {
            $fact = ['category' => 'preferences', 'key' => 'meetings', 'value' => 'Después de las 10'];
            MemoryService::upsertFact($user->id, $fact);
            MemoryService::upsertFact($user->id, array_merge($fact, ['value' => 'Después de las 11']));
            MemoryService::upsertFact($user->id, $fact + ['action' => 'delete']);
            $this->assertDatabaseCount('user_profile_facts', 0);
            MemoryService::saveFacts($user->id, [$fact]);

            return UserProfileFact::where('user_id', $user->id)->exists();
        }
        if ($probe === 'stale_memory') {
            $conv = $this->conversation($user->id, ['summary' => 'Resumen nuevo', 'summarized_message_count' => 12]);
            $this->conversation($this->user()->id, ['summary' => 'OTHER_USER_PRIVATE']);
            $this->summaryResponse();
            app(ConversationSummaryService::class)->maybeSummarize($conv, array_fill(0, 4, ['role' => 'user', 'content' => 'Mensaje antiguo']), true);

            return [$conv->fresh()->summarized_message_count, str_contains(MemoryService::buildContextBlock($user->id), 'OTHER_USER_PRIVATE')];
        }
        if ($probe === 'optional_audio') {
            Http::fake(['api.openai.com/v1/audio/speech' => Http::response([], 503)]);
            $this->provider->append($this->text(), $this->text());
            $this->postJson('/generate-text', ['prompt' => 'Hola', 'generateAudio' => false])->assertOk();
            $before = Http::recorded()->count();
            $response = $this->postJson('/generate-text', ['prompt' => 'Hola', 'generateAudio' => true])->assertOk();

            return [$before, ! empty($response->json('choices.0.message.content'))];
        }
        if ($probe === 'streaming') {
            // F5-03: el streaming es opcional (Accept: text/event-stream) para no romper a los clientes JSON.
            $this->provider->append(new Response(200, ['Content-Type' => 'text/event-stream'],
                "data: ".json_encode(['type' => 'response.output_text.delta', 'delta' => 'Ho'])."\n\n"
                ."data: ".json_encode(['type' => 'response.completed', 'response' => ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hola']]]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 2]]])."\n\n"));
            $response = $this->withHeaders(['Accept' => 'text/event-stream'])->post('/generate-text', ['prompt' => 'Hola', 'generateAudio' => false])->assertOk();
            $content = $response->streamedContent();

            return str_contains($response->headers->get('Content-Type'), 'text/event-stream')
                && strpos($content, 'event: delta') !== false && strpos($content, 'event: delta') < strpos($content, 'event: done');
        }
        throw new \LogicException('Missing probe: '.$probe);
    }
}
