<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\ReminderIntegration;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\SecurityTestCase;

/** RC0: las respuestas reales conservan las claves del contrato v1 compartido con Hermes/Android. */
class ContextualReminderContractTest extends SecurityTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => ['database/migrations/2026_05_22_101002_create_device_tokens_table.php',
            'database/migrations/2026_09_23_100000_create_contextual_reminders_tables.php',
            'database/migrations/2026_09_23_110000_add_reminder_dispatch.php', 'database/migrations/2026_09_23_120000_add_reminder_device_receipts.php'], '--force' => true])->assertExitCode(0);
        config(['reminders.hermes_api_enabled' => true, 'reminders.mobile_api_enabled' => true]);
        Carbon::setTestNow(Carbon::parse('2026-09-21T14:00:00Z'));
        $owner = $this->user();
        [, $this->token] = ReminderIntegration::issue($owner, 'hermes');
        $device = DeviceToken::create(['user_id' => $owner->id, 'token' => 'fcm-'.Str::random(40), 'capabilities' => ['contextual_reminders_v1']]);
        config(['reminders.dispatch_device_allowlist' => [$device->id]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/contextual-reminders/'.$name), true, flags: JSON_THROW_ON_ERROR);
    }

    private function hermes(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$this->token, 'Idempotency-Key' => (string) Str::uuid()])
            ->json($method, '/api/integrations/hermes/v1/reminders'.$uri, $data);
    }

    /** Compara claves y valores, ignorando los marcadores variables. */
    private function assertMatchesFixture(array $fixture, array $actual): void
    {
        $this->assertSame(array_keys($fixture), array_keys($actual), 'Cambió el contrato v1: versionarlo y coordinar clientes.');
        foreach ($fixture as $key => $value) {
            if (is_array($value)) {
                $this->assertMatchesFixture($value, $actual[$key]);
            } elseif (! in_array($value, ['<uuid>', '<request-id>'], true)) {
                $this->assertSame($value, $actual[$key], "Valor de contrato distinto en {$key}");
            }
        }
    }

    public function test_create_detail_and_conflict_match_v1_fixtures(): void
    {
        $created = $this->hermes('POST', '', $this->fixture('hermes-create-request.json'))->assertCreated()->json();
        $this->assertMatchesFixture($this->fixture('hermes-create-response.json'), $created);

        $this->assertMatchesFixture($this->fixture('reminder-detail-response.json'), $this->hermes('GET', '/'.$created['id'])->assertOk()->json());

        $this->hermes('POST', '/'.$created['id'].'/snooze', ['expected_version' => 1, 'minutes' => 60])->assertOk();
        $conflict = $this->hermes('POST', '/'.$created['id'].'/complete', ['expected_version' => 1])->assertStatus(409)->json();
        $this->assertMatchesFixture($this->fixture('conflict-response.json'), $conflict);
    }

    public function test_push_payload_proposal_is_private_small_and_string_only(): void
    {
        $push = $this->fixture('push-contextual-reminder-v1.json');

        $this->assertSame('contextual_reminder', $push['type']);
        $this->assertSame('1', $push['schema_version']);
        foreach ($push as $key => $value) {
            $this->assertIsString($value, "FCM data exige strings: {$key}");
        }
        foreach (['context', 'next_action', 'token', 'audio_url', 'url'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $push);
        }
        $this->assertLessThan(3 * 1024, strlen(json_encode($push)));
    }
}
