<?php

namespace Tests\Feature;

use App\Models\AiInteraction;
use App\Models\ApiUsageLog;
use App\Models\Conversation;
use App\Services\AiTelemetry;
use App\Services\AiTransport;
use App\Services\ConversationSummaryService;
use App\Services\FcmService;
use App\Services\InteractionTracker;
use App\Services\TtsService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\MailFake;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Tests\SecurityTestCase;

class F1ObservabilityTest extends SecurityTestCase
{
    private function textResponse(): Response
    {
        return new Response(200, [], json_encode(['model' => 'gpt-4.1-2025-04-14',
            'output' => [['type' => 'web_search_call'], ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Respuesta ficticia.']]]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 200, 'input_tokens_details' => ['cached_tokens' => 400]]]));
    }

    public function test_chat_and_auxiliary_audio_share_a_trace_and_never_duplicate_or_store_content(): void
    {
        $this->actingAs($this->user());
        $this->provider->append($this->textResponse());
        Http::fake(['api.openai.com/v1/audio/speech' => Http::response('fake-mp3', 200)]);
        $result = $this->postJson('/generate-text', ['prompt' => 'PRIVATE_INPUT_MARKER', 'generateAudio' => true])->assertOk();
        $id = $result->json('interaction_id');
        $result->assertHeader('X-Interaction-ID', $id)->assertJsonPath('choices.0.message.content', 'Respuesta ficticia.');
        $logs = ApiUsageLog::where('interaction_id', $id)->get();
        $this->assertCount(2, $logs);
        $text = $logs->firstWhere('api_type', 'text_generation');
        $this->assertSame('gpt-4.1-2025-04-14', $text->model);
        $this->assertSame('0.02800000', $text->estimated_cost);
        $this->assertSame(1, $text->metadata['web_search_calls']);
        $this->assertSame(400, $text->metadata['cached_tokens']);
        $this->assertSame('estimated', $logs->firstWhere('api_type', 'tts')->cost_status);
        $interaction = AiInteraction::findOrFail($id);
        $this->assertSame('responded', $interaction->result);
        $this->assertNull($interaction->task_achieved);
        $this->assertContains('context', array_column($interaction->stages, 'stage'));
        $this->assertStringNotContainsString('PRIVATE_INPUT_MARKER', $logs->toJson().$interaction->toJson());
        $this->assertStringNotContainsString('Respuesta ficticia.', $logs->toJson().$interaction->toJson());
    }

    public function test_event_extraction_is_accounted_with_the_actual_model(): void
    {
        $this->actingAs($this->user());
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'model' => 'gpt-4.1-mini-2025-04-14', 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100],
            'choices' => [['message' => ['content' => '{"title":null}']]],
        ])]);
        $this->provider->append($this->textResponse());
        $id = $this->postJson('/generate-text', ['prompt' => 'Agéndame una llamada', 'generateAudio' => false])->assertOk()->json('interaction_id');
        $this->assertSame(2, ApiUsageLog::where('interaction_id', $id)->count());
        $this->assertDatabaseHas('api_usage_logs', ['interaction_id' => $id, 'stage' => 'agenda_extract', 'estimated_cost' => 0.00056]);
    }

    public function test_effective_grok_model_and_http_failure_are_measured_without_inventing_a_fallback(): void
    {
        $user = $this->user();
        $user->forceFill(['ai_provider' => 'grok'])->save();
        $this->actingAs($user);
        $this->provider->append(new Response(200, [], json_encode(['model' => 'grok-4-1-fast-reasoning', 'choices' => [['message' => ['content' => 'Hola']]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]])));
        $this->postJson('/generate-text', ['prompt' => 'Hola', 'generateAudio' => false])->assertOk();
        $this->assertDatabaseHas('api_usage_logs', ['api_provider' => 'grok', 'model' => 'grok-4-1-fast-reasoning', 'estimated_cost' => null, 'cost_status' => 'unknown_tariff']);
        $this->provider->append(new Response(504, [], '{"error":"timeout"}'));
        // Existing Grok flow uses http_errors=false and returns HTTP 200 even for
        // this provider failure. F1 records it honestly; fixing the contract is F5.
        $id = $this->postJson('/generate-text', ['prompt' => 'Hola otra vez', 'generateAudio' => false])->assertOk()->json('interaction_id');
        $logs = ApiUsageLog::where('interaction_id', $id)->orderBy('id')->get();
        $this->assertCount(1, $logs);
        $this->assertSame('grok', $logs->first()->api_provider);
        $this->assertSame('unknown_failure', $logs->first()->cost_status);
        $this->assertSame(504, $logs->first()->metadata['status_code']);
        $this->assertSame('error', AiInteraction::find($id)->result);
    }

    public function test_vision_requests_are_separate_from_plain_text_in_the_report(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['model' => 'gpt-4o-2024-08-06', 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]])]);
        Http::post('https://api.openai.com/v1/chat/completions', ['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => [['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,FAKE']]]]]]);
        $this->assertDatabaseHas('api_usage_logs', ['api_type' => 'image_analysis', 'cost_status' => 'estimated']);
        $this->assertStringNotContainsString('base64', ApiUsageLog::first()->toJson());
    }

    public function test_plain_list_response_contract_is_preserved_and_traced_via_header(): void
    {
        $user = $this->user();
        $request = Request::create('/fake-resource', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => new Route('GET', '/fake-resource', ['uses' => 'App\\Http\\Controllers\\AgendaController@index']));
        $response = app(InteractionTracker::class)->run($request, fn () => response()->json([['id' => 1]]));
        $this->assertSame([['id' => 1]], $response->getData(true));
        $this->assertNotEmpty($response->headers->get('X-Interaction-ID'));
        $this->assertDatabaseCount('ai_interactions', 1);
    }

    public function test_telemetry_failure_cannot_consume_or_replace_a_provider_response(): void
    {
        $dispatcher = ApiUsageLog::getEventDispatcher();
        ApiUsageLog::setEventDispatcher(clone $dispatcher);
        ApiUsageLog::creating(fn () => throw new \RuntimeException('Simulated telemetry storage outage'));
        try {
            $this->provider->append(new Response(200, [], '{"output":[],"model":"gpt-4.1"}'));
            $response = app(AiTransport::class)->client()->post('https://api.openai.com/v1/responses', ['json' => ['model' => 'gpt-4.1']]);
            $this->assertSame('{"output":[],"model":"gpt-4.1"}', $response->getBody()->getContents());
            $this->assertTrue($this->aiLogs->hasErrorRecords());
        } finally {
            ApiUsageLog::setEventDispatcher($dispatcher);
        }
    }

    public function test_feedback_requires_authentication_for_both_clients(): void
    {
        $id = (string) Str::uuid();
        foreach (['/interactions/', '/api/mobile/interactions/'] as $prefix) {
            $this->postJson($prefix.$id.'/feedback', ['useful' => true, 'task_achieved' => true, 'corrections' => 0])->assertUnauthorized();
        }
    }

    public function test_summary_and_memory_extraction_are_one_real_call_attributed_to_owner(): void
    {
        config(['services.openai.api_key' => 'fake-test-key']);
        $user = $this->user();
        $conversation = Conversation::create(['user_id' => $user->id, 'title' => 'Ficticia', 'content' => '{}', 'type' => 'chat']);
        $this->provider->append(new Response(200, [], json_encode([
            'model' => 'gpt-4o-mini-2024-07-18', 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 100],
            'choices' => [['message' => ['content' => json_encode(['summary' => 'Acuerdo ficticio', 'extracted_facts' => [['category' => 'work_context', 'key' => 'hora', 'value' => '10:00']]])]]],
        ])));
        app(ConversationSummaryService::class)->maybeSummarize($conversation, array_fill(0, 4, ['role' => 'user', 'content' => 'PRIVATE_TRANSCRIPT']));
        $this->assertDatabaseCount('api_usage_logs', 1);
        $this->assertDatabaseHas('api_usage_logs', ['user_id' => $user->id, 'stage' => 'summary_memory', 'estimated_cost' => 0.00021]);
        $this->assertDatabaseHas('user_profile_facts', ['user_id' => $user->id, 'key' => 'hora']);
        $this->assertSame('Acuerdo ficticio', $conversation->fresh()->summary);
    }

    public function test_image_usage_updates_reservation_once_and_preserves_response_body(): void
    {
        $this->actingAs($this->user());
        $this->provider->append(new Response(200, [], json_encode(['model' => 'gpt-image-1',
            'data' => [['b64_json' => base64_encode('fake-image')]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 1000, 'input_tokens_details' => ['text_tokens' => 100, 'image_tokens' => 0]],
        ])));
        $this->postJson('/generate-image', ['prompt' => 'Un gato'])->assertOk()->assertJsonStructure(['image_url']);
        $this->assertDatabaseCount('api_usage_logs', 1);
        $this->assertDatabaseHas('api_usage_logs', ['status' => 'success', 'estimated_cost' => 0.0405, 'cost_status' => 'estimated']);
    }

    public function test_stt_uses_duration_and_preserves_transcription(): void
    {
        $this->actingAs($this->user());
        $this->provider->append(new Response(200, [], '{"text":"Hola","duration":90}'));
        $this->postJson('/speech-to-text', ['audio' => UploadedFile::fake()->create('audio.mp3', 2, 'audio/mpeg')])->assertOk()->assertJsonPath('text', 'Hola');
        $this->assertDatabaseHas('api_usage_logs', ['api_type' => 'stt', 'estimated_cost' => 0.009, 'cost_status' => 'estimated']);
    }

    public function test_retries_record_each_attempt_and_tts_is_measured_in_characters(): void
    {
        Http::fake(['api.openai.com/v1/audio/speech' => Http::sequence()->push('failure', 503)->push('fake-mp3', 200)]);
        $this->assertNotNull(app(TtsService::class)->generateMp3('áéíóú'));
        $this->assertDatabaseCount('api_usage_logs', 2);
        $this->assertDatabaseHas('api_usage_logs', ['cost_status' => 'unknown_failure', 'estimated_cost' => null]);
        $log = ApiUsageLog::where('status', 'success')->firstOrFail();
        $this->assertSame(5, $log->metadata['characters']);
        $this->assertSame('0.00007500', $log->estimated_cost);
        $this->assertSame(0, $log->total_tokens);
    }

    public function test_network_failure_records_unknown_not_free(): void
    {
        Http::fake(['api.openai.com/*' => Http::failedConnection()]);
        try {
            Http::post('https://api.openai.com/v1/responses', ['model' => 'gpt-4.1']);
        } catch (ConnectionException $e) {
        }
        $this->assertDatabaseCount('api_usage_logs', 1);
        $this->assertDatabaseHas('api_usage_logs', ['status' => 'error', 'cost_status' => 'unknown_failure', 'estimated_cost' => null]);
    }

    public function test_background_retries_share_an_id_and_restore_attribution_after_the_job(): void
    {
        $owner = $this->user();
        Http::fake(['api.openai.com/v1/audio/speech' => Http::sequence()->push([], 503)->push('fake-mp3', 200)]);
        $telemetry = app(AiTelemetry::class);
        $telemetry->forUser($owner->id, fn () => app(TtsService::class)->generateMp3('Hola'));
        $logs = ApiUsageLog::get();
        $this->assertCount(2, $logs);
        $this->assertSame([$owner->id], $logs->pluck('user_id')->unique()->values()->all());
        $this->assertCount(1, $logs->pluck('interaction_id')->unique());
        $this->assertNull($telemetry->userId);
        $this->assertNull(app(InteractionTracker::class)->id);
    }

    public function test_feedback_is_owned_validated_and_supports_mobile_without_affecting_xp(): void
    {
        $owner = $this->user();
        $this->actingAs($owner);
        $id = $this->postJson('/generate-text', ['prompt' => 'Qué hora es', 'generateAudio' => false])->assertOk()->json('interaction_id');
        $payload = ['useful' => true, 'task_achieved' => false, 'corrections' => 2];
        $this->postJson('/interactions/'.$id.'/feedback', $payload + ['result' => 'hacked'])->assertOk();
        $this->assertDatabaseHas('ai_interactions', ['id' => $id, 'useful' => true, 'task_achieved' => false, 'corrections' => 2, 'result' => 'responded']);
        $this->postJson('/api/mobile/interactions/'.$id.'/feedback', ['useful' => false, 'task_achieved' => false, 'corrections' => 0])->assertOk();
        $this->postJson('/interactions/'.$id.'/feedback', ['useful' => true, 'task_achieved' => true, 'corrections' => -1])->assertUnprocessable();
        $this->actingAs($this->user())->postJson('/interactions/'.$id.'/feedback', $payload)->assertNotFound();
        $this->assertSame('responded', AiInteraction::find($id)->result);
    }

    public function test_failed_interaction_is_traced_and_client_cannot_choose_its_id(): void
    {
        $response = $this->actingAs($this->user())->postJson('/generate-text', ['prompt' => '', 'interaction_id' => 'client-choice'])->assertUnprocessable();
        $trace = AiInteraction::firstOrFail();
        $response->assertHeader('X-Interaction-ID', $trace->id)->assertJsonPath('interaction_id', $trace->id);
        $this->assertSame(422, $trace->http_status);
        $this->assertSame('error', $trace->result);
        $this->assertNotSame('client-choice', $trace->id);
    }

    public function test_external_transports_are_blocked_by_default(): void
    {
        foreach (['https://api.openai.com/v1/responses', 'https://telegram.example.test/send', 'https://nextcloud.example.test/dav', 'https://fcm.googleapis.com/messages'] as $url) {
            try {
                Http::post($url);
                $this->fail('Unmocked network allowed');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('without a matching fake', $e->getMessage());
            }
        }
        try {
            app(AiTransport::class)->client()->get('https://api.openai.com/v1/models');
            $this->fail('Unmocked Guzzle allowed');
        } catch (\OutOfBoundsException $e) {
            $this->assertStringContainsString('Mock queue is empty', $e->getMessage());
        }
        $method = new \ReflectionMethod(FcmService::class, 'getAccessToken');
        try {
            $method->invoke(new FcmService);
            $this->fail('Real FCM credentials allowed');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('disabled in tests', $e->getMessage());
        }
        $this->assertInstanceOf(MailFake::class, Mail::getFacadeRoot());
        $this->assertInstanceOf(NotificationFake::class, Notification::getFacadeRoot());
    }

    public function test_admin_reports_unknown_legacy_costs_and_consistent_filters_and_csv(): void
    {
        $admin = $this->user('admin');
        $admin->update(['name' => '=FORMULA()']);
        $other = $this->user();
        $base = ['user_id' => $admin->id, 'api_provider' => 'openai', 'api_type' => 'text_generation', 'model' => 'gpt-4.1', 'created_at' => '2026-09-21 23:59:59'];
        foreach ([['status' => 'success', 'estimated_cost' => 0.003, 'cost_status' => 'estimated'], ['status' => 'error', 'estimated_cost' => null, 'cost_status' => 'unknown_failure'], ['status' => 'success', 'estimated_cost' => 99]] as $values) {
            ApiUsageLog::create($base + $values)->forceFill(['created_at' => $base['created_at']])->save();
        }
        ApiUsageLog::create(array_merge($base, ['user_id' => $other->id, 'estimated_cost' => 200, 'cost_status' => 'estimated']));
        $filter = '?date_from=2026-09-21&date_to=2026-09-21&user_id='.$admin->id.'&api_type=text_generation';
        $response = $this->actingAs($admin)->getJson('/admin/api-usage/stats'.$filter)->assertOk();
        $this->assertSame(3, $response->json('summary.total_requests'));
        $this->assertEquals(0.003, $response->json('summary.total_cost'));
        $this->assertSame(2, $response->json('summary.unknown_costs'));
        $this->assertEqualsWithDelta(66.66667, $response->json('summary.success_rate'), 0.00001);
        $this->assertSame(3, $response->json('by_type.0.count'));
        $csv = $this->get('/admin/api-usage/export'.$filter)->assertOk()->streamedContent();
        $this->assertSame(2, substr_count($csv, 'Costo desconocido'));
        $this->assertStringContainsString("'=FORMULA()", $csv);
        $this->get('/admin/api-usage'.$filter)->assertOk()->assertSee('2 solicitudes con costo desconocido');
        $this->get('/admin/api-usage/'.ApiUsageLog::first()->id)->assertOk()->assertSee('Sin prompts');
        $this->getJson('/admin/api-usage/stats?date_from=invalid')->assertUnprocessable();
        $this->actingAs($other)->getJson('/admin/api-usage/stats')->assertForbidden();
    }
}
