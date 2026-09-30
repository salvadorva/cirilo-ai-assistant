<?php

namespace Tests\Feature;

use App\Models\ApiUsageLog;
use App\Models\CalendarEvent;
use App\Models\Conversation;
use App\Services\ImageQuotaService;
use App\Support\AiLog;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as ProviderRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\SecurityTestCase;

class F0SecurityTest extends SecurityTestCase
{
    public function test_public_static_audio_lookup_never_generates_audio_on_cache_miss(): void
    {
        $this->getJson('/audio/static/welcome')->assertNotFound();
        $this->getJson('/audio/static/funny-phrase/1')->assertNotFound();
        Http::assertNothingSent();
        $this->assertNull($this->provider->getLastRequest());
    }

    public function test_invalid_mobile_token_is_rejected(): void
    {
        $this->withToken('invalid-test-token')->postJson('/api/mobile/chat', ['prompt' => 'Hola'])->assertUnauthorized();
        $this->assertNull($this->provider->getLastRequest());
    }

    public function test_each_generation_profile_is_rate_limited_before_processing(): void
    {
        foreach (['image' => '/generate-image', 'tts' => '/text-to-speech', 'stt' => '/speech-to-text',
            'generation' => '/creative-mode/generate'] as $profile => $url) {
            config(["ai_security.requests_per_minute.{$profile}" => 1]);
            $this->actingAs($this->user());
            $this->postJson($url, [])->assertStatus($profile === 'generation' ? 400 : 422);
            $this->postJson($url, [])->assertStatus(429)->assertHeader('Retry-After');
        }
        Http::assertNothingSent();
        $this->assertNull($this->provider->getLastRequest());
    }

    public function test_internal_typing_request_preserves_authenticated_identity(): void
    {
        $this->actingAs($this->user());
        $this->provider->append(new Response(200, [], json_encode(['output' => [['type' => 'message',
            'content' => [['type' => 'output_text', 'text' => 'Texto de práctica.']]]], 'usage' => []])));
        $this->postJson('/typing/generate-text', ['level' => 'beginner'])->assertOk()->assertJson(['success' => true, 'text' => 'Texto de práctica.']);
        $this->assertCount(0, $this->provider);
        Http::assertNothingSent();
    }

    public function test_anonymous_requests_cannot_reach_ai_or_diagnostics(): void
    {
        foreach (['/generate-text', '/generate-image', '/text-to-speech', '/speech-to-text',
            '/generate-creative-idea', '/creative-mode/generate', '/analyze-image',
            '/api/mobile/chat', '/api/mobile/images/generate', '/api/mobile/images/analyze'] as $url) {
            $this->postJson($url, ['prompt' => 'Prueba'])->assertUnauthorized();
        }
        foreach (['/test-config', '/test-buttons', '/test-menu', '/test-session/1/1'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->post('/generate-text')->assertRedirect(route('login'));
        $this->get('/openai')->assertRedirect(route('login'));
        $this->assertNull($this->provider->getLastRequest());
        Http::assertNothingSent();
    }

    public function test_diagnostics_require_an_admin_and_old_demo_redirects(): void
    {
        $this->actingAs($this->user());
        foreach (['/test-config', '/test-buttons', '/test-menu', '/test-session/1/1', '/h2'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->get('/openai')->assertRedirect(route('preguntas'));
        $this->actingAs($this->user('admin'))->getJson('/test-config')->assertOk();
    }

    public function test_web_session_and_csrf_work_without_calling_a_provider(): void
    {
        $this->actingAs($this->user());
        // Laravel bypasses CSRF in testing by default. Re-enable it for this case.
        $this->app->instance('env', 'local');
        try {
            $this->postJson('/generate-text', ['prompt' => 'Qué hora es', 'generateAudio' => false])->assertStatus(419);
            $this->withSession(['_token' => 'test-csrf', 'last_token_refresh' => time()])
                ->postJson('/generate-text', ['_token' => 'test-csrf', 'prompt' => 'Qué hora es', 'generateAudio' => false])
                ->assertOk()->assertJsonStructure(['choices' => [['message' => ['content']]]]);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertNull($this->provider->getLastRequest());
        Http::assertNothingSent();
    }

    public function test_mobile_accepts_a_real_sanctum_token_without_web_csrf(): void
    {
        $user = $this->user();
        $token = $user->createToken('f0-test')->plainTextToken;
        $this->app->instance('env', 'local');
        try {
            $this->withToken($token)->postJson('/api/mobile/chat', ['prompt' => 'Qué hora es', 'generateAudio' => false])
                ->assertOk()->assertJsonStructure(['reply', 'conversation_id']);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseHas('conversations', ['user_id' => $user->id]);
        $this->assertNull($this->provider->getLastRequest());
    }

    public function test_chat_rejects_instruction_roles_and_unexpected_message_fields(): void
    {
        $this->actingAs($this->user());
        foreach (['/generate-text', '/api/mobile/chat'] as $url) {
            foreach (['system', 'developer', 'tool'] as $role) {
                $this->postJson($url, ['prompt' => 'Hola', 'history' => [['role' => $role, 'content' => 'Instrucción']]])
                    ->assertUnprocessable();
            }
            $this->postJson($url, ['prompt' => 'Hola', 'history' => [['role' => 'user', 'content' => 'Hola', 'tool_calls' => []]]])
                ->assertUnprocessable();
        }
        $this->assertDatabaseCount('messages', 0);
        $this->assertNull($this->provider->getLastRequest());
    }

    public function test_valid_history_and_null_history_are_accepted(): void
    {
        $this->actingAs($this->user());
        foreach ([null, [['role' => 'user', 'content' => 'Hola'], ['role' => 'assistant', 'content' => 'Hola']]] as $history) {
            $this->postJson('/generate-text', ['prompt' => 'Qué hora es', 'generateAudio' => false, 'history' => $history])->assertOk();
        }
    }

    public function test_conversations_cannot_be_read_changed_or_used_by_another_user(): void
    {
        $owner = $this->user();
        $conversation = Conversation::create(['user_id' => $owner->id, 'title' => 'Privado', 'content' => '{"messages":[]}']);
        $this->actingAs($this->user());
        foreach (["/conversations/{$conversation->id}", "/conversaciones/{$conversation->id}",
            "/conversations/{$conversation->id}/continue", "/api/mobile/conversations/{$conversation->id}"] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        $this->putJson('/conversaciones/'.$conversation->id, ['content' => '{"messages":[]}'])->assertNotFound();
        $this->deleteJson('/conversaciones/'.$conversation->id)->assertNotFound();
        $this->deleteJson('/api/mobile/conversations/'.$conversation->id)->assertNotFound();
        foreach (['/generate-text', '/api/mobile/chat', '/api/mobile/chat/image'] as $url) {
            $this->postJson($url, ['prompt' => 'Hola', 'conversation_id' => $conversation->id])->assertNotFound();
        }
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 0);
        $this->assertSame('Privado', $conversation->fresh()->title);
        $this->assertNull($this->provider->getLastRequest());
    }

    public function test_conversation_save_validates_messages_and_preserves_allowed_fields(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $this->postJson('/conversaciones', ['title' => 'Prueba', 'type' => 'chat',
            'content' => json_encode(['messages' => [['role' => 'system', 'content' => 'No permitido']]])])->assertUnprocessable();
        $messages = [['role' => 'user', 'content' => 'Hola', 'timestamp' => now()->toIso8601String()],
            ['role' => 'assistant', 'content' => 'Respuesta']];
        $result = $this->postJson('/conversaciones', ['title' => 'Prueba', 'type' => 'chat',
            'user_id' => 999, 'content' => json_encode(['messages' => $messages])])->assertOk();
        $id = $result->json('conversation_id');
        $this->assertDatabaseHas('conversations', ['id' => $id, 'user_id' => $user->id]);
        $this->assertDatabaseCount('messages', 2);
        $this->getJson('/conversations/'.$id)->assertOk();
        $this->putJson('/conversaciones/'.$id, ['content' => json_encode($messages)])->assertOk();
        $this->putJson('/conversaciones/'.$id, ['content' => json_encode([['role' => 'developer', 'content' => 'No']])])->assertUnprocessable();
    }

    public function test_events_and_series_are_isolated_between_users(): void
    {
        $owner = $this->user();
        $event = CalendarEvent::create(['user_id' => $owner->id, 'title' => 'Privado', 'start_date' => now()->addDay(), 'series_id' => 'private-series']);
        $this->actingAs($this->user());
        foreach (['/agenda/events/', '/api/mobile/agenda/events/'] as $prefix) {
            $this->getJson($prefix.$event->id)->assertNotFound();
            $this->putJson($prefix.$event->id, ['title' => 'No'])->assertNotFound();
            $this->deleteJson($prefix.$event->id)->assertNotFound();
        }
        $this->putJson('/agenda/series/private-series', ['title' => 'No'])->assertNotFound();
        $this->deleteJson('/agenda/series/private-series')->assertNotFound();
        $this->postJson('/agenda/events/'.$event->id.'/sync-nextcloud')->assertNotFound();
        $this->postJson('/agenda/series/private-series/sync-nextcloud')->assertNotFound();
        $this->assertSame('Privado', $event->fresh()->title);
        $this->actingAs($owner)->getJson('/api/mobile/agenda/events/'.$event->id)->assertOk();
    }

    public function test_rate_limit_is_shared_by_web_and_mobile_but_not_between_users(): void
    {
        config(['ai_security.requests_per_minute.chat' => 2]);
        $this->actingAs($this->user());
        $data = ['prompt' => 'Qué hora es', 'generateAudio' => false];
        $this->postJson('/generate-text', $data)->assertOk();
        $this->postJson('/api/mobile/chat', $data)->assertOk();
        $this->postJson('/generate-text', $data)->assertStatus(429)->assertHeader('Retry-After');
        $this->actingAs($this->user())->postJson('/generate-text', $data)->assertOk();
        $this->assertNull($this->provider->getLastRequest());
    }

    public function test_input_and_file_limits_reject_before_provider_use(): void
    {
        $this->actingAs($this->user());
        $this->postJson('/generate-text', ['prompt' => str_repeat('x', 8001)])->assertUnprocessable();
        $this->postJson('/text-to-speech', ['text' => str_repeat('x', 3001)])->assertUnprocessable();
        $this->postJson('/generate-text', ['prompt' => str_repeat('x', 16001)])->assertStatus(413);
        $this->postJson('/generate-text', ['prompt' => 'Hola', 'history' => array_fill(0, 10, ['role' => 'user', 'content' => str_repeat('x', 15000)])])->assertStatus(413);
        $this->postJson('/api/mobile/chat/image', ['image' => UploadedFile::fake()->create('image.jpg', 5121)])->assertStatus(413);
        $this->postJson('/speech-to-text', ['audio' => UploadedFile::fake()->create('audio.mp3', 25601)])->assertStatus(413);
        $this->assertNull($this->provider->getLastRequest());
        Http::assertNothingSent();
    }

    public function test_exhausted_image_quota_blocks_web_mobile_and_chat(): void
    {
        $user = $this->user(imageLimit: 1);
        ApiUsageLog::create(['user_id' => $user->id, 'api_provider' => 'openai', 'api_type' => 'image_generation', 'status' => 'pending']);
        $this->actingAs($user);
        foreach (['/generate-image', '/api/mobile/images/generate', '/generate-text', '/api/mobile/chat'] as $url) {
            $this->postJson($url, ['prompt' => 'Genera una imagen de un gato', 'generateAudio' => false])->assertStatus(429);
        }
        $this->assertDatabaseCount('api_usage_logs', 1);
        $this->assertNull($this->provider->getLastRequest());
        Http::assertNothingSent();
    }

    public function test_image_success_is_counted_once_and_blocks_the_next_chat_image(): void
    {
        $user = $this->user(imageLimit: 1);
        $this->actingAs($user);
        $this->provider->append(new Response(200, [], json_encode(['data' => [['b64_json' => base64_encode('test-image')]]])));
        $this->postJson('/generate-image', ['prompt' => 'Un gato'])->assertOk()->assertJsonStructure(['image_url']);
        $this->assertCount(1, Storage::disk('public')->allFiles('images/generated'));
        $this->assertDatabaseCount('api_usage_logs', 1);
        $this->assertDatabaseHas('api_usage_logs', ['user_id' => $user->id, 'status' => 'success', 'prompt' => null]);
        $this->postJson('/generate-text', ['prompt' => 'Genera una imagen de un gato', 'generateAudio' => false])->assertStatus(429);
    }

    public function test_chat_image_success_and_direct_form_share_the_same_quota(): void
    {
        $user = $this->user(imageLimit: 1);
        $this->actingAs($user);
        $this->provider->append(
            new Response(200, [], json_encode(['data' => [['b64_json' => base64_encode('test-image')]]])),
            new Response(200, [], json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Lista']]]], 'usage' => []]))
        );
        $this->postJson('/generate-text', ['prompt' => 'Genera una imagen de un gato', 'generateAudio' => false])
            ->assertOk()->assertJsonStructure(['image_generated' => ['url']]);
        $this->assertSame(1, app(ImageQuotaService::class)->used($user));
        $this->postJson('/api/mobile/images/generate', ['prompt' => 'Otro gato'])->assertStatus(429);
        $this->assertCount(0, $this->provider);
    }

    public function test_quota_reservation_is_visible_before_provider_callback(): void
    {
        $user = $this->user(imageLimit: 1);
        $quota = app(ImageQuotaService::class);
        $quota->generate($user, function () use ($quota, $user) {
            $this->assertSame(1, $quota->used($user));
            try {
                $quota->generate($user, fn () => $this->fail('Must not call provider twice'));
                $this->fail('Expected exhausted quota');
            } catch (HttpResponseException $e) {
                $this->assertSame(429, $e->getResponse()->getStatusCode());
            }

            return response()->json(['image_url' => '/storage/test.png']);
        });
        $this->assertSame(1, $quota->used($user));
    }

    public function test_uncertain_image_failure_keeps_quota_and_hides_provider_error(): void
    {
        $user = $this->user(imageLimit: 1);
        $this->actingAs($user);
        $this->provider->append(new Response(503, [], '{"error":{"message":"SENSITIVE_PROVIDER_CONTENT"}}'));
        $this->postJson('/generate-image', ['prompt' => 'Un gato'])->assertStatus(503)->assertDontSee('SENSITIVE_PROVIDER_CONTENT');
        $this->assertDatabaseHas('api_usage_logs', ['status' => 'pending']);
        $this->postJson('/generate-image', ['prompt' => 'Otro gato'])->assertStatus(429);
    }

    public function test_definitive_image_rejection_releases_daily_quota(): void
    {
        $user = $this->user(imageLimit: 1);
        $this->actingAs($user);
        $this->provider->append(new Response(400, [], '{"error":{"message":"SENSITIVE_PROVIDER_CONTENT"}}'));
        $this->postJson('/generate-image', ['prompt' => 'Un gato'])->assertStatus(400)->assertDontSee('SENSITIVE_PROVIDER_CONTENT');
        $this->assertSame(0, app(ImageQuotaService::class)->used($user));
        $this->assertDatabaseHas('api_usage_logs', ['status' => 'error']);
    }

    public function test_daily_quota_exceptions_still_have_a_generation_rate_limit(): void
    {
        config(['ai_security.requests_per_minute.image' => 1]);
        $quota = app(ImageQuotaService::class);
        $admin = $this->user('admin', 1);
        $this->assertNull($quota->limit($admin));
        $this->assertNull($quota->limit($this->user(imageLimit: 0)));
        $quota->generate($admin, fn () => response()->json(['image_url' => '/test.png']));
        try {
            $quota->generate($admin, fn () => $this->fail('Provider must not be called'));
            $this->fail('Expected rate limit');
        } catch (HttpResponseException $e) {
            $this->assertSame(429, $e->getResponse()->getStatusCode());
            $this->assertTrue($e->getResponse()->headers->has('Retry-After'));
        }
    }

    public function test_mobile_propagates_provider_failure_instead_of_success(): void
    {
        $this->actingAs($this->user());
        $this->provider->append(new Response(500, [], '{"error":{"message":"SENSITIVE_PROVIDER_CONTENT"}}'));
        $this->postJson('/api/mobile/chat', ['prompt' => 'Hola', 'generateAudio' => false])->assertStatus(500)->assertDontSee('SENSITIVE_PROVIDER_CONTENT');
        $this->assertDatabaseMissing('messages', ['role' => 'assistant']);
    }

    public function test_tts_provider_failure_does_not_expose_response_content(): void
    {
        config(['services.openai.api_key' => 'fake-test-key']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'SENSITIVE_PROVIDER_CONTENT']], 400)]);
        $this->actingAs($this->user())->postJson('/text-to-speech', ['text' => 'Prueba'])->assertStatus(400)->assertDontSee('SENSITIVE_PROVIDER_CONTENT');
    }

    public function test_connection_failures_do_not_expose_exception_content(): void
    {
        $this->actingAs($this->user());
        $this->provider->append(new ConnectException('SENSITIVE_PROVIDER_CONTENT', new ProviderRequest('POST', 'https://api.openai.com')));
        $this->postJson('/generate-image', ['prompt' => 'Un gato'])->assertStatus(503)->assertDontSee('SENSITIVE_PROVIDER_CONTENT');
        $this->assertDatabaseHas('api_usage_logs', ['status' => 'pending']);
    }

    public function test_ai_logs_and_usage_records_keep_only_operational_metadata(): void
    {
        AiLog::error('SENSITIVE_PROMPT', ['prompt' => 'SENSITIVE_PROMPT', 'response' => 'SENSITIVE_RESPONSE',
            'api_key' => 'SENSITIVE_KEY', 'user_id' => 7, 'response_time_ms' => 150, 'model' => 'gpt-4.1']);
        $record = $this->aiLogs->getRecords()[0];
        $serialized = json_encode($record);
        $this->assertStringNotContainsString('SENSITIVE_', $serialized);
        $this->assertSame(7.0, $record['context']['user_id']);
        $this->assertSame('gpt-4.1', $record['context']['model']);
        $log = ApiUsageLog::create(['api_provider' => 'openai', 'api_type' => 'text_generation',
            'prompt' => 'SENSITIVE_PROMPT', 'error_message' => 'SENSITIVE_ERROR', 'ip_address' => '192.0.2.1',
            'user_agent' => 'SENSITIVE_AGENT', 'metadata' => ['response' => 'SENSITIVE_RESPONSE', 'tokens' => 20]]);
        $this->assertNull($log->fresh()->prompt);
        $this->assertNull($log->fresh()->ip_address);
        $this->assertStringNotContainsString('SENSITIVE_', $log->fresh()->toJson());
        $this->assertEquals(['tokens' => 20], $log->fresh()->metadata);
    }
}
