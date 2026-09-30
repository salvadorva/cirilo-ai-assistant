<?php

namespace Tests\Feature;

use App\Models\ContextualReminder;
use App\Models\DeviceToken;
use App\Models\ReminderDelivery;
use App\Models\ReminderIntegration;
use App\Models\User;
use App\Services\FcmService;
use App\Services\Reminders\Push\PushTransport;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\Fakes\FakePushTransport;
use Tests\SecurityTestCase;

/** RC3 (backend): instalación y capacidad del teléfono, acciones disponibles y recibos. Sin Android ni FCM real. */
class ContextualReminderDeviceTest extends SecurityTestCase
{
    private User $owner;

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
        $this->at('2026-09-21T14:00:00Z');
        $this->push = new FakePushTransport;
        $this->app->instance(PushTransport::class, $this->push);
        $this->mock(FcmService::class, fn ($mock) => $mock->shouldNotReceive('sendToUser'));
        config(['reminders.hermes_api_enabled' => true, 'reminders.mobile_api_enabled' => true, 'reminders.contextual_dispatch_enabled' => true]);
        $this->owner = $this->user();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc)->setTimezone(config('app.timezone')));
    }

    private function mobile(string $method, string $uri, array $data = [], ?User $user = null, array $headers = [])
    {
        $token = ($user ?? $this->owner)->createToken('android')->plainTextToken;
        $response = $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$token] + $headers)->json($method, $uri, $data);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function register(array $data, ?User $user = null)
    {
        return $this->mobile('POST', '/api/mobile/device-token', $data, $user);
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/contextual-reminders/'.$name)), true, flags: JSON_THROW_ON_ERROR);
    }

    private function reminder(array $overrides = []): ContextualReminder
    {
        return ContextualReminder::create($overrides + [
            'user_id' => $this->owner->id, 'title' => 'Enviar propuesta', 'context' => 'Contexto', 'next_action' => 'Acción',
            'scheduled_at' => '2026-09-21 14:30:00', 'expires_at' => '2026-09-21 16:00:00', 'timezone' => 'America/Guatemala',
            'state' => 'pending', 'version' => 1, 'confirmed_by_user' => true, 'confirmed_at' => '2026-09-21 13:59:00',
        ]);
    }

    // ---- Registro de instalación y capacidad ----

    public function test_legacy_registration_without_installation_keeps_working_and_is_not_ready(): void
    {
        $this->register(['token' => 'legacy-token', 'platform' => 'android'])->assertOk()->assertJsonPath('message', 'ok');
        $device = DeviceToken::sole();
        $this->assertNull($device->installation_id);
        $this->assertNull($device->capabilities);
    }

    public function test_capable_installation_registers_known_capabilities_only(): void
    {
        $response = $this->register($this->fixture('device-registration-request.json') + ['token' => 'fcm-1'])->assertOk()
            ->assertJsonPath('message', 'ok')
            ->assertJsonPath('capabilities', ['contextual_reminders_v1'])
            ->assertJsonPath('contextual_reminders_ready', false); // aún no seleccionado para el piloto
        $device = DeviceToken::sole();
        $this->assertSame('0f8c2b1e-7a4d-4a5e-9b61-3c2d1e0f9a8b', $device->installation_id);
        $this->assertSame('1.4.0', $device->app_version);

        config(['reminders.dispatch_device_allowlist' => [$device->id]]);
        $this->register($this->fixture('device-registration-request.json') + ['token' => 'fcm-1'])->assertJsonPath('contextual_reminders_ready', true);
        $this->assertArrayNotHasKey('token', $response->json());
    }

    public function test_token_rotation_updates_the_same_installation(): void
    {
        $data = $this->fixture('device-registration-request.json');
        $this->register($data + ['token' => 'fcm-old'])->assertOk();
        $id = DeviceToken::sole()->id;
        DeviceToken::whereKey($id)->update(['disabled_at' => now()]);

        $this->register($data + ['token' => 'fcm-new'])->assertOk();
        $device = DeviceToken::sole();
        $this->assertSame($id, $device->id);
        $this->assertSame('fcm-new', $device->token);
        $this->assertNull($device->disabled_at);
    }

    public function test_legacy_row_for_same_token_is_adopted_not_duplicated(): void
    {
        $this->register(['token' => 'fcm-1'])->assertOk();
        $this->register($this->fixture('device-registration-request.json') + ['token' => 'fcm-1'])->assertOk();
        $this->assertSame(1, DeviceToken::count());
        $this->assertNotNull(DeviceToken::sole()->installation_id);
    }

    public function test_installation_of_another_user_cannot_be_appropriated(): void
    {
        $data = $this->fixture('device-registration-request.json');
        $this->register($data + ['token' => 'fcm-1'])->assertOk();
        $intruder = $this->user();

        $this->register($data + ['token' => 'fcm-2'], $intruder)->assertStatus(409)->assertJsonPath('code', 'installation_conflict');
        $this->register(['installation_id' => (string) Str::uuid(), 'token' => 'fcm-1', 'capabilities' => ['contextual_reminders_v1']], $intruder)
            ->assertStatus(409)->assertJsonPath('code', 'installation_conflict');
        $this->assertSame($this->owner->id, DeviceToken::sole()->user_id);
        $this->assertSame('fcm-1', DeviceToken::sole()->token);
    }

    public function test_downgraded_app_loses_capability_and_invalid_input_is_rejected(): void
    {
        $data = $this->fixture('device-registration-request.json');
        $this->register($data + ['token' => 'fcm-1'])->assertOk();
        $this->register(['token' => 'fcm-1', 'installation_id' => $data['installation_id'], 'app_version' => '1.3.0'])->assertOk()
            ->assertJsonPath('capabilities', []);
        $this->assertSame([], DeviceToken::sole()->capabilities);

        $this->register(['token' => 'fcm-1', 'installation_id' => 'x'])->assertStatus(422);
        $this->register(['token' => 'fcm-1', 'installation_id' => $data['installation_id'], 'capabilities' => 'contextual_reminders_v1'])->assertStatus(422);
        $this->register(['token' => 'fcm-1', 'installation_id' => $data['installation_id'], 'capabilities' => array_fill(0, 11, 'x')])->assertStatus(422);
    }

    // ---- Contrato de acciones disponibles ----

    public function test_detail_exposes_allowed_actions_bounded_by_expiry(): void
    {
        $reminder = $this->reminder();
        $this->mobile('GET', '/api/mobile/contextual-reminders/'.$reminder->id)->assertOk()
            ->assertJsonPath('actions', ['complete' => true, 'cancel' => true, 'snooze_minutes' => [15, 30, 60]])
            ->assertJsonPath('delivery_receipt', ['received_at' => null, 'displayed_at' => null]);

        $this->at('2026-09-21T15:40:00Z'); // quedan 20 min
        $this->mobile('GET', '/api/mobile/contextual-reminders/'.$reminder->id)->assertJsonPath('actions.snooze_minutes', [15]);

        $this->at('2026-09-21T16:00:00Z');
        $this->mobile('GET', '/api/mobile/contextual-reminders/'.$reminder->id)
            ->assertJsonPath('state', 'expired')->assertJsonPath('actions', ['complete' => false, 'cancel' => false, 'snooze_minutes' => []]);
    }

    // ---- Recibos ----

    private function readyDevice(): DeviceToken
    {
        $this->register($this->fixture('device-registration-request.json') + ['token' => 'fcm-1'])->assertOk();
        $device = DeviceToken::sole();
        config(['reminders.dispatch_device_allowlist' => [$device->id]]);

        return $device;
    }

    public function test_receipts_are_idempotent_diagnostics_that_never_close_the_reminder(): void
    {
        $this->readyDevice();
        $reminder = $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->artisan('reminders:dispatch')->assertExitCode(0);
        $receipt = $this->fixture('receipt-request.json');

        $this->at('2026-09-21T14:30:05Z');
        $this->mobile('POST', "/api/mobile/contextual-reminders/{$reminder->id}/receipts", $receipt)->assertOk()->assertJsonPath('recorded', true);
        $this->at('2026-09-21T14:31:00Z');
        $this->mobile('POST', "/api/mobile/contextual-reminders/{$reminder->id}/receipts", $receipt)->assertOk()->assertJsonPath('recorded', true);

        $delivery = ReminderDelivery::sole();
        $this->assertSame('2026-09-21 14:30:05', $delivery->getRawOriginal('displayed_at'));
        $this->assertSame('2026-09-21 14:30:05', $delivery->getRawOriginal('received_at'));
        $this->assertSame(['pending', 1], [$reminder->fresh()->state, $reminder->fresh()->version]);
        $this->mobile('GET', '/api/mobile/contextual-reminders/'.$reminder->id)
            ->assertJsonPath('delivery_receipt', ['received_at' => '2026-09-21T14:30:05Z', 'displayed_at' => '2026-09-21T14:30:05Z']);
    }

    public function test_receipt_validation_and_scope(): void
    {
        $this->readyDevice();
        $reminder = $this->reminder();
        $uri = "/api/mobile/contextual-reminders/{$reminder->id}/receipts";
        $receipt = $this->fixture('receipt-request.json');

        // Sin despacho para esa versión: se acepta pero no se registra nada.
        $this->mobile('POST', $uri, $receipt)->assertOk()->assertJsonPath('recorded', false);
        $this->mobile('POST', $uri, ['event' => 'opened'] + $receipt)->assertStatus(422);
        $this->mobile('POST', $uri, ['installation_id' => (string) Str::uuid()] + $receipt)->assertStatus(422)->assertJsonPath('code', 'unknown_installation');
        $this->mobile('POST', $uri, $receipt + ['state' => 'completed'])->assertStatus(422);
        $this->mobile('POST', $uri, $receipt, $this->user())->assertNotFound();
    }

    // ---- Recorrido completo local ----

    public function test_end_to_end_register_create_dispatch_display_and_complete(): void
    {
        $device = $this->readyDevice();
        [, $hermes] = ReminderIntegration::issue($this->owner, 'hermes');
        $created = $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$hermes, 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/integrations/hermes/v1/reminders', $this->fixture('hermes-create-request.json'))->assertCreated();
        $id = $created->json('id');

        $this->at('2026-09-21T14:30:00Z');
        $this->artisan('reminders:dispatch')->assertExitCode(0);
        $this->assertCount(1, $this->push->sent);
        $this->assertSame($device->id, $this->push->sent[0]['device_id']);
        $this->assertSame(['title' => 'Cirilo', 'body' => 'Tienes un recordatorio acordado'],
            array_intersect_key($this->push->sent[0]['data'], ['title' => 1, 'body' => 1]));

        $this->mobile('POST', "/api/mobile/contextual-reminders/{$id}/receipts", $this->fixture('receipt-request.json'))->assertJsonPath('recorded', true);
        $detail = $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}")->assertOk()->json();
        $this->assertSame('accepted', $detail['dispatch']['state']);

        $this->mobile('POST', "/api/mobile/contextual-reminders/{$id}/complete", ['expected_version' => $detail['version']], null,
            ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->assertJsonPath('state', 'completed');
        $this->at('2026-09-21T14:45:00Z');
        $this->artisan('reminders:dispatch')->assertExitCode(0);
        $this->assertCount(1, $this->push->sent);
    }
}
