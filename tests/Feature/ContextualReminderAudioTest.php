<?php

namespace Tests\Feature;

use App\Jobs\GenerateContextualReminderAudio;
use App\Models\ApiUsageLog;
use App\Models\ContextualReminder;
use App\Models\DeviceToken;
use App\Models\ReminderIntegration;
use App\Models\User;
use App\Services\FcmService;
use App\Services\Reminders\ContextualReminderAudio;
use App\Services\Reminders\Push\PushTransport;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Fakes\FakePushTransport;
use Tests\SecurityTestCase;

/** RC4: audio privado opcional con TTS falso. El texto funciona siempre por sí solo. */
class ContextualReminderAudioTest extends SecurityTestCase
{
    private User $owner;

    private string $hermes;

    private FakePushTransport $push;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => array_map(fn ($f) => 'database/migrations/'.$f, [
            '2026_07_02_100000_create_focus_slots_table.php',
            '2026_05_22_101002_create_device_tokens_table.php',
            '2026_09_23_100000_create_contextual_reminders_tables.php',
            '2026_09_23_110000_add_reminder_dispatch.php',
            '2026_09_23_120000_add_reminder_device_receipts.php',
        ]), '--force' => true])->assertExitCode(0);
        Carbon::setTestNow(Carbon::parse('2026-09-21T14:00:00Z')->setTimezone(config('app.timezone')));
        Storage::fake('local');
        $this->push = new FakePushTransport;
        $this->app->instance(PushTransport::class, $this->push);
        $this->mock(FcmService::class, fn ($mock) => $mock->shouldNotReceive('sendToUser'));
        $this->owner = $this->user();
        $device = DeviceToken::create(['user_id' => $this->owner->id, 'token' => 'fcm-1', 'installation_id' => (string) Str::uuid(),
            'capabilities' => ['contextual_reminders_v1']]);
        [, $this->hermes] = ReminderIntegration::issue($this->owner, 'hermes');
        config(['reminders.hermes_api_enabled' => true, 'reminders.mobile_api_enabled' => true, 'reminders.contextual_dispatch_enabled' => true,
            'reminders.dispatch_device_allowlist' => [$device->id], 'reminders.audio_enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function create(array $overrides = []): string
    {
        $payload = json_decode(file_get_contents(base_path('tests/Fixtures/contextual-reminders/hermes-create-request.json')), true);

        return $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$this->hermes, 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/integrations/hermes/v1/reminders', array_merge($payload, ['with_audio' => true, 'voice' => 'nova'], $overrides))
            ->assertCreated()->json('id');
    }

    private function mobile(string $method, string $uri, ?User $user = null)
    {
        $token = ($user ?? $this->owner)->createToken('android')->plainTextToken;
        $response = $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$token])->json($method, $uri);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function tick(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc)->setTimezone(config('app.timezone')));
        $this->artisan('reminders:dispatch')->assertExitCode(0);
    }

    public function test_approved_pilot_default_keeps_audio_off(): void
    {
        $this->assertFalse((require config_path('reminders.php'))['audio_enabled']);
    }

    public function test_private_audio_is_generated_from_fixed_template_and_served_only_to_owner_while_pending(): void
    {
        Http::fake(['api.openai.com/v1/audio/speech' => Http::response('FAKE_MP3_BYTES', 200, ['Content-Type' => 'audio/mpeg'])]);
        $id = $this->create();

        Http::assertSent(fn (Request $request) => $request['input'] === 'Recordatorio acordado: Enviar propuesta a Ana. Siguiente acción: Abrir el borrador y enviarlo por correo.'
            && $request['voice'] === 'nova');
        $reminder = ContextualReminder::find($id);
        Storage::disk('local')->assertExists($reminder->audio_path);
        $this->assertStringStartsWith('reminders/audio/', $reminder->audio_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($this->owner->id, ApiUsageLog::where('api_type', 'tts')->sole()->user_id);

        $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}")->assertJsonPath('audio_ready', true);
        $audio = $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}/audio")->assertOk();
        $this->assertSame('FAKE_MP3_BYTES', $audio->streamedContent());
        $this->assertStringContainsString('no-store', $audio->headers->get('Cache-Control'));
        $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}/audio", $this->user())->assertNotFound();

        $this->tick('2026-09-21T14:30:00Z');
        $this->assertSame('true', $this->push->sent[0]['data']['audio_ready']);

        $reminder->forceFill(['state' => 'completed', 'version' => 2])->save();
        $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}/audio")->assertNotFound()->assertJsonPath('code', 'audio_not_available');
    }

    public function test_tts_failure_falls_back_to_text_without_delaying_or_blocking(): void
    {
        Http::fake(['api.openai.com/v1/audio/speech' => Http::response('', 503)]);
        $id = $this->create();

        $this->assertNull(ContextualReminder::find($id)->audio_path);
        $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}")->assertJsonPath('audio_ready', false);
        $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}/audio")->assertNotFound();
        $this->tick('2026-09-21T14:30:00Z');
        $this->assertCount(1, $this->push->sent);
        $this->assertSame('false', $this->push->sent[0]['data']['audio_ready']);
    }

    public function test_audio_ready_later_does_not_trigger_another_push(): void
    {
        Http::fake(['api.openai.com/v1/audio/speech' => Http::response('FAKE_MP3_BYTES', 200)]);
        Queue::fake([GenerateContextualReminderAudio::class]);
        $id = $this->create();

        $this->tick('2026-09-21T14:30:00Z');
        $this->assertCount(1, $this->push->sent);
        $this->assertSame('false', $this->push->sent[0]['data']['audio_ready']);

        app(ContextualReminderAudio::class)->generate($id); // el job termina tarde
        $this->tick('2026-09-21T14:31:00Z');
        $this->assertCount(1, $this->push->sent);
        $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}")->assertJsonPath('audio_ready', true);
    }

    public function test_audio_is_not_generated_for_closed_or_expired_reminders(): void
    {
        Http::fake(['api.openai.com/v1/audio/speech' => Http::response('FAKE_MP3_BYTES', 200)]);
        Queue::fake([GenerateContextualReminderAudio::class]);
        $id = $this->create();
        ContextualReminder::whereKey($id)->update(['state' => 'cancelled', 'version' => 2]);
        app(ContextualReminderAudio::class)->generate($id);
        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
