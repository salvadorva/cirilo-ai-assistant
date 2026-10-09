<?php

namespace Tests\Feature;

use App\Console\Commands\SendFocusMessages;
use App\Jobs\DispatchReminderDelivery;
use App\Models\ContextualReminder;
use App\Models\DeviceToken;
use App\Models\FocusSlot;
use App\Models\ReminderDelivery;
use App\Models\ReminderIntegration;
use App\Models\User;
use App\Services\FcmService;
use App\Services\Reminders\ContextualReminderService;
use App\Services\Reminders\Push\PushResult;
use App\Services\Reminders\Push\PushTransport;
use App\Services\Reminders\ReminderDestinations;
use App\Services\Reminders\ReminderDispatcher;
use App\Services\TtsService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Fakes\FakePushTransport;
use Tests\SecurityTestCase;

/** RC2: despacho durable con FCM falso. Ningún envío real ni credencial. */
class ReminderDispatchTest extends SecurityTestCase
{
    private FakePushTransport $push;

    private User $owner;

    private DeviceToken $phone;

    protected function setUp(): void
    {
        parent::setUp();
        $migrations = [
            '2026_07_02_100000_create_focus_slots_table.php',
            '2026_07_03_100000_add_with_audio_to_focus_slots_table.php',
            '2026_07_07_100000_add_audio_path_to_focus_slots_table.php',
            '2026_05_22_101002_create_device_tokens_table.php',
            '2026_09_23_100000_create_contextual_reminders_tables.php',
            '2026_09_23_110000_add_reminder_dispatch.php',
            '2026_09_23_120000_add_reminder_device_receipts.php',
        ];
        $this->artisan('migrate', ['--path' => array_map(fn ($f) => 'database/migrations/'.$f, $migrations), '--force' => true])->assertExitCode(0);
        $this->at('2026-09-21T14:00:00Z'); // lunes 08:00 Guatemala
        $this->push = new FakePushTransport;
        $this->app->instance(PushTransport::class, $this->push);
        $this->mock(FcmService::class, fn ($mock) => $mock->shouldNotReceive('sendToUser'));
        app(Kernel::class)->registerCommand(new SendFocusMessages(app(FcmService::class), app(TtsService::class)));

        $this->owner = $this->user();
        $this->phone = $this->device($this->owner);
        config([
            'reminders.hermes_api_enabled' => true,
            'reminders.mobile_api_enabled' => true,
            'reminders.contextual_dispatch_enabled' => true,
            'reminders.dispatch_device_allowlist' => [$this->phone->id],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function device(User $user, array $capabilities = ['contextual_reminders_v1']): DeviceToken
    {
        return DeviceToken::create(['user_id' => $user->id, 'token' => 'fcm-'.Str::random(40), 'platform' => 'android',
            'installation_id' => (string) Str::uuid(), 'capabilities' => $capabilities]);
    }

    private function reminder(array $overrides = []): ContextualReminder
    {
        return ContextualReminder::create($overrides + [
            'user_id' => $this->owner->id, 'title' => 'Enviar propuesta', 'context' => 'CONTEXTO_PRIVADO', 'next_action' => 'ACCION_PRIVADA',
            'scheduled_at' => '2026-09-21 14:30:00', 'expires_at' => '2026-09-21 16:00:00', 'timezone' => 'America/Guatemala',
            'state' => 'pending', 'version' => 1, 'confirmed_by_user' => true, 'confirmed_at' => '2026-09-21 13:59:00',
        ]);
    }

    /** Reloj de prueba en la zona de la app, como el reloj real de producción. */
    private function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc)->setTimezone(config('app.timezone')));
    }

    private function tick(): void
    {
        $this->artisan('reminders:dispatch')->assertExitCode(0);
    }

    private function dispatcher(): ReminderDispatcher
    {
        return app(ReminderDispatcher::class);
    }

    private function slot(array $overrides = []): FocusSlot
    {
        return FocusSlot::create($overrides + ['user_id' => $this->owner->id, 'time' => '08:00', 'days' => [1], 'title' => 'Cierre',
            'message' => 'Mensaje de rutina', 'enabled' => true, 'with_audio' => false]);
    }

    private function enableRoutines(string $cutover = '2026-09-21T00:00:00Z'): void
    {
        config(['reminders.routine_dispatch_enabled' => true, 'reminders.routine_dispatch_cutover_at' => $cutover]);
    }

    // ---- Flags y selección ----

    public function test_defaults_keep_every_dispatch_path_disabled(): void
    {
        $defaults = require config_path('reminders.php');
        $this->assertFalse($defaults['contextual_dispatch_enabled']);
        $this->assertFalse($defaults['routine_dispatch_enabled']);
        $this->assertSame([], $defaults['dispatch_device_allowlist']);
        $this->assertSame([], $defaults['dispatch_user_allowlist']);

        config(['reminders.contextual_dispatch_enabled' => false]);
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $this->assertSame(0, ReminderDelivery::count());
        $this->assertSame([], $this->push->sent);
    }

    public function test_nothing_is_sent_before_the_scheduled_time(): void
    {
        $this->reminder();
        $this->at('2026-09-21T14:29:59Z');
        $this->tick();
        $this->assertSame(0, ReminderDelivery::count());
    }

    public function test_due_reminder_is_accepted_once_with_private_payload_and_remaining_ttl(): void
    {
        $reminder = $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $this->tick();
        $this->tick();

        $this->assertCount(1, $this->push->sent);
        $sent = $this->push->sent[0];
        $this->assertSame($this->phone->id, $sent['device_id']);
        $this->assertSame(5400, $sent['ttl']);
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/contextual-reminders/push-contextual-reminder-v1.json')), true);
        $this->assertSame(array_keys($fixture), array_keys($sent['data']));
        $this->assertSame(['contextual_reminder', '1', $reminder->id, $reminder->id, '1', '2026-09-21T14:30:00Z', '2026-09-21T16:00:00Z', 'Cirilo', 'Tienes un recordatorio acordado', 'false'],
            array_values($sent['data']));
        $this->assertStringNotContainsString('PRIVAD', json_encode($sent['data']));

        $delivery = ReminderDelivery::sole();
        $this->assertSame('accepted', $delivery->status);
        $this->assertSame('projects/test/messages/1', $delivery->provider_message_id);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->accepted_at);
        // Aceptación FCM no completa el recordatorio.
        $this->assertSame('pending', $reminder->fresh()->state);
        $this->assertSame(1, $reminder->fresh()->version);
    }

    public function test_one_delivery_per_eligible_destination_only(): void
    {
        $second = $this->device($this->owner);
        $this->device($this->owner, []); // app antigua sin capacidad
        $this->device($this->user()); // otro usuario
        $disabled = $this->device($this->owner);
        $disabled->forceFill(['disabled_at' => now()])->save();
        config(['reminders.dispatch_device_allowlist' => [$this->phone->id, $second->id, $disabled->id]]);
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();

        $this->assertEqualsCanonicalizing([$this->phone->id, $second->id], array_column($this->push->sent, 'device_id'));
    }

    public function test_a_user_in_the_pilot_receives_on_the_latest_installation_and_survives_a_reinstall(): void
    {
        config(['reminders.dispatch_device_allowlist' => [], 'reminders.dispatch_user_allowlist' => [$this->owner->id]]);
        $this->phone->forceFill(['last_used_at' => '2026-09-20 10:00:00'])->save();
        $stale = $this->device($this->owner);
        $stale->forceFill(['last_used_at' => '2026-09-01 10:00:00'])->save(); // instalación vieja nunca dada de baja
        $this->device($this->owner, [])->forceFill(['last_used_at' => '2026-09-21 09:00:00'])->save(); // más reciente pero sin capacidad
        $destinations = app(ReminderDestinations::class);

        $this->assertSame([$this->phone->id], $destinations->contextual($this->owner->id)->pluck('id')->all());
        $this->assertSame([], $destinations->contextual($this->user()->id)->all(), 'Otro usuario no está en el piloto.');

        // Reinstalar crea otra instalación: pasa a ser el destino sin tocar la configuración.
        $reinstalled = $this->device($this->owner);
        $reinstalled->forceFill(['last_used_at' => '2026-09-21 13:00:00'])->save();
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();

        $this->assertSame([$reinstalled->id], array_column($this->push->sent, 'device_id'));
        $this->assertFalse($destinations->isContextualDestination($this->phone, $this->owner->id));
    }

    // ---- Durabilidad y claims ----

    public function test_delivery_persisted_but_never_enqueued_is_recovered_by_next_tick(): void
    {
        Queue::fake();
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        Queue::assertPushed(DispatchReminderDelivery::class, 1);
        $this->tick(); // job todavía en cola: no se re-encola
        Queue::assertPushed(DispatchReminderDelivery::class, 1);

        $this->at('2026-09-21T14:32:00Z'); // el job se perdió (caída tras commit)
        $this->tick();
        Queue::assertPushed(DispatchReminderDelivery::class, 2);
        (new DispatchReminderDelivery(ReminderDelivery::sole()->id))->handle($this->dispatcher());
        $this->assertSame('accepted', ReminderDelivery::sole()->status);
        $this->assertCount(1, $this->push->sent);
    }

    public function test_duplicate_jobs_do_not_send_twice(): void
    {
        Queue::fake();
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $id = ReminderDelivery::sole()->id;
        $this->dispatcher()->deliver($id);
        $this->dispatcher()->deliver($id);
        $this->assertCount(1, $this->push->sent);
    }

    public function test_worker_cannot_claim_a_delivery_held_by_another_live_lease(): void
    {
        Queue::fake();
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $delivery = ReminderDelivery::sole();
        $delivery->forceFill(['status' => 'processing', 'claim_token' => (string) Str::uuid(), 'lease_expires_at' => now()->addMinutes(2)])->save();

        $this->dispatcher()->deliver($delivery->id);
        $this->assertSame([], $this->push->sent);
    }

    public function test_abandoned_claim_is_uncertain_and_retried_at_most_once(): void
    {
        Queue::fake();
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $delivery = ReminderDelivery::sole();
        // Worker muerto después de iniciar la llamada de red.
        $delivery->forceFill(['status' => 'processing', 'claim_token' => (string) Str::uuid(), 'attempts' => 1,
            'attempted_at' => now(), 'in_flight_since' => now(), 'lease_expires_at' => now()->addSeconds(120)])->save();

        $this->at('2026-09-21T14:32:01Z');
        $this->tick();
        $delivery->refresh();
        $this->assertSame('retry_wait', $delivery->status);
        $this->assertSame(1, $delivery->uncertain_count);

        $delivery->forceFill(['status' => 'processing', 'claim_token' => (string) Str::uuid(), 'attempts' => 2,
            'attempted_at' => now(), 'in_flight_since' => now(), 'lease_expires_at' => now()->addSeconds(120)])->save();
        $this->at('2026-09-21T14:34:02Z');
        $this->tick();
        $this->assertSame('uncertain', $delivery->fresh()->status);
    }

    public function test_abandoned_claim_before_network_returns_to_pending_without_counting_as_uncertain(): void
    {
        Queue::fake();
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $delivery = ReminderDelivery::sole();
        $delivery->forceFill(['status' => 'processing', 'claim_token' => (string) Str::uuid(), 'lease_expires_at' => now()->addSeconds(120)])->save();

        $this->at('2026-09-21T14:32:01Z');
        $this->tick();
        $delivery->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(0, $delivery->uncertain_count);
        $this->assertNull($delivery->claim_token);
    }

    public function test_result_of_a_lost_lease_is_fenced(): void
    {
        Queue::fake();
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $delivery = ReminderDelivery::sole();
        $this->push->push(function () use ($delivery) {
            // Otro worker recuperó el claim mientras este esperaba a FCM.
            ReminderDelivery::whereKey($delivery->id)->update(['claim_token' => 'otro-worker']);

            return PushResult::accepted('projects/test/messages/tarde');
        });
        $this->dispatcher()->deliver($delivery->id);

        $delivery->refresh();
        $this->assertSame('processing', $delivery->status);
        $this->assertSame('otro-worker', $delivery->claim_token);
        $this->assertNull($delivery->provider_message_id);
    }

    // ---- Resultados FCM ----

    public function test_retryable_errors_back_off_respecting_retry_after_and_stop_after_three_attempts(): void
    {
        $this->push->push(PushResult::retryable(503, 'unavailable'), PushResult::retryable(429, 'quota_exceeded', 300), PushResult::retryable(503, 'unavailable'));
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $delivery = ReminderDelivery::sole();
        $this->assertSame('retry_wait', $delivery->status);
        $this->assertTrue($delivery->next_attempt_at->gte(Carbon::parse('2026-09-21T14:31:00Z')));
        $this->assertTrue($delivery->next_attempt_at->lte(Carbon::parse('2026-09-21T14:31:12Z')));

        $this->at('2026-09-21T14:30:59Z');
        $this->tick();
        $this->assertCount(1, $this->push->sent);

        $this->at('2026-09-21T14:31:15Z');
        $this->tick();
        $delivery->refresh();
        $this->assertSame('retry_wait', $delivery->status);
        $this->assertTrue($delivery->next_attempt_at->gte(Carbon::parse('2026-09-21T14:36:15Z'))); // Retry-After 300 s

        $this->at('2026-09-21T14:40:00Z');
        $this->tick();
        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('unavailable', $delivery->error_category);
        $this->assertSame(3, $delivery->attempts);
        $this->at('2026-09-21T15:00:00Z');
        $this->tick();
        $this->assertCount(3, $this->push->sent);
    }

    public function test_retry_is_not_scheduled_beyond_expiry(): void
    {
        $this->push->push(PushResult::retryable(429, 'quota_exceeded', 7200));
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $delivery = ReminderDelivery::sole();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('quota_exceeded', $delivery->error_category);
    }

    public function test_unregistered_token_is_disabled_not_deleted_and_reminder_stays_pending(): void
    {
        $this->push->push(PushResult::unregistered(404));
        $reminder = $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();

        $this->assertSame('failed', ReminderDelivery::sole()->status);
        $this->assertSame('unregistered', ReminderDelivery::sole()->error_category);
        $this->assertNotNull($this->phone->fresh()->disabled_at);
        $this->assertSame('pending', $reminder->fresh()->state);

        // La app vuelve a registrar el mismo token: queda habilitado otra vez.
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/mobile/device-token', ['token' => $this->phone->token])->assertOk();
        $this->assertNull($this->phone->fresh()->disabled_at);
    }

    public function test_invalid_payload_fails_without_touching_the_token(): void
    {
        $this->push->push(PushResult::invalidPayload(400));
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $this->assertSame('invalid_argument', ReminderDelivery::sole()->error_category);
        $this->assertSame('failed', ReminderDelivery::sole()->status);
        $this->assertNull($this->phone->fresh()->disabled_at);
    }

    public function test_auth_or_configuration_error_is_visible_and_not_retried(): void
    {
        $this->push->push(PushResult::auth(401));
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $this->at('2026-09-21T14:35:00Z');
        $this->tick();
        $this->assertSame('failed', ReminderDelivery::sole()->status);
        $this->assertSame('auth', ReminderDelivery::sole()->error_category);
        $this->assertCount(1, $this->push->sent);
    }

    public function test_timeout_is_uncertain_with_a_single_retry(): void
    {
        $this->push->push(PushResult::uncertain(), PushResult::uncertain());
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $this->assertSame('retry_wait', ReminderDelivery::sole()->status);
        $this->at('2026-09-21T14:32:00Z');
        $this->tick();
        $this->at('2026-09-21T14:40:00Z');
        $this->tick();
        $this->assertSame('uncertain', ReminderDelivery::sole()->status);
        $this->assertCount(2, $this->push->sent);
    }

    // ---- Estados de negocio y versiones ----

    public function test_expiry_without_attempt_skips_and_expires_reminder(): void
    {
        Queue::fake();
        $reminder = $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $this->at('2026-09-21T16:00:00Z'); // la cola estuvo caída toda la ventana
        $this->tick();
        $this->dispatcher()->deliver(ReminderDelivery::sole()->id);

        $this->assertSame([], $this->push->sent);
        $this->assertSame('skipped', ReminderDelivery::sole()->status);
        $this->assertSame('expired', ReminderDelivery::sole()->error_category);
        $this->assertSame('expired', $reminder->fresh()->state);
        $this->assertSame(2, $reminder->fresh()->version);
    }

    public function test_cancel_before_send_skips_pending_delivery(): void
    {
        Queue::fake();
        $reminder = $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        app(ContextualReminderService::class)->transition($reminder, 'cancel', ['expected_version' => 1], ['user', $this->owner->id], (string) Str::uuid(), 'mobile');

        $this->dispatcher()->deliver(ReminderDelivery::sole()->id);
        $this->assertSame([], $this->push->sent);
        $this->assertSame('skipped', ReminderDelivery::sole()->status);
        $this->assertSame('superseded', ReminderDelivery::sole()->error_category);
    }

    public function test_snooze_supersedes_old_revision_and_sends_new_revision_at_new_time(): void
    {
        Queue::fake();
        $reminder = $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $old = ReminderDelivery::sole();
        app(ContextualReminderService::class)->transition($reminder, 'snooze', ['expected_version' => 1, 'minutes' => 15], ['user', $this->owner->id], (string) Str::uuid(), 'mobile');

        $this->tick();
        $this->assertSame('skipped', $old->fresh()->status);
        $this->dispatcher()->deliver($old->id); // job viejo tras nueva versión
        $this->assertSame([], $this->push->sent);

        $this->at('2026-09-21T14:45:00Z');
        $this->tick();
        $new = ReminderDelivery::where('revision', 2)->sole();
        $this->dispatcher()->deliver($new->id);
        $this->assertCount(1, $this->push->sent);
        $this->assertSame('2', $this->push->sent[0]['data']['version']);
    }

    public function test_late_provider_result_never_reopens_closed_reminder(): void
    {
        $reminder = $this->reminder();
        $this->push->push(function () use ($reminder) {
            $reminder->forceFill(['state' => 'cancelled', 'version' => 2, 'cancelled_at' => now()])->save();

            return PushResult::accepted('projects/test/messages/1');
        });
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $this->assertSame('accepted', ReminderDelivery::sole()->status);
        $this->assertSame('cancelled', $reminder->fresh()->state);
        $this->assertSame(2, $reminder->fresh()->version);
    }

    public function test_api_reports_dispatch_state_of_current_revision(): void
    {
        [$integration, $token] = ReminderIntegration::issue($this->owner, 'hermes');
        $reminder = $this->reminder(['reminder_integration_id' => $integration->id]);
        $detail = fn () => $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integrations/hermes/v1/reminders/'.$reminder->id)->assertOk();
        $detail()->assertJsonPath('dispatch.state', 'not_started');

        $this->push->push(PushResult::retryable(503, 'unavailable'));
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();
        $detail()->assertJsonPath('dispatch.state', 'retry_wait')->assertJsonPath('dispatch.error_category', 'unavailable');

        $this->at('2026-09-21T14:32:00Z');
        $this->tick();
        $detail()->assertJsonPath('dispatch.state', 'accepted')->assertJsonPath('dispatch.error_category', null);
    }

    public function test_logs_do_not_contain_context_or_fcm_tokens(): void
    {
        $spy = new TestHandler;
        Log::extend('spy', fn () => new Logger('spy', [$spy]));
        config(['logging.channels.spy' => ['driver' => 'spy'], 'logging.default' => 'spy']);
        Log::forgetChannel('spy');
        $this->push->push(PushResult::retryable(503, 'unavailable'));
        $this->reminder();
        $this->at('2026-09-21T14:30:00Z');
        $this->tick();

        $logs = json_encode(array_map(fn ($r) => $r->toArray(), $spy->getRecords()));
        $this->assertStringContainsString('reminder_delivery', $logs);
        $this->assertStringNotContainsString('PRIVAD', $logs);
        $this->assertStringNotContainsString(substr($this->phone->token, 0, 12), $logs);
    }

    // ---- Creación exige destino listo ----

    public function test_create_requires_a_ready_destination(): void
    {
        [, $token] = ReminderIntegration::issue($this->owner, 'hermes');
        $create = fn () => $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/integrations/hermes/v1/reminders', json_decode(file_get_contents(base_path('tests/Fixtures/contextual-reminders/hermes-create-request.json')), true));

        config(['reminders.dispatch_device_allowlist' => []]);
        $create()->assertStatus(409)->assertJsonPath('code', 'device_not_ready');
        config(['reminders.dispatch_device_allowlist' => [$this->phone->id]]);
        $this->phone->forceFill(['capabilities' => []])->save();
        $create()->assertStatus(409)->assertJsonPath('code', 'device_not_ready');
        $this->phone->forceFill(['capabilities' => ['contextual_reminders_v1'], 'disabled_at' => now()])->save();
        $create()->assertStatus(409)->assertJsonPath('code', 'device_not_ready');
        $this->assertSame(0, ContextualReminder::count());

        $this->phone->forceFill(['disabled_at' => null])->save();
        $create()->assertCreated();
    }

    // ---- Rutinas: corrección de last_sent_at ----

    public function test_routine_failure_does_not_mark_last_sent_at_and_legacy_path_stays_out(): void
    {
        $this->enableRoutines();
        $slot = $this->slot();
        $this->push->push(PushResult::retryable(503, 'unavailable'));
        $this->tick();
        $this->artisan('focus:send-messages')->assertExitCode(0); // no debe enviar por el camino legado

        $this->assertNull($slot->fresh()->last_sent_at);
        $this->assertSame('retry_wait', ReminderDelivery::sole()->status);
        $this->assertSame('routine', ReminderDelivery::sole()->source_type);
        $this->assertCount(1, $this->push->sent);
    }

    public function test_routine_acceptance_sets_last_sent_at_and_retries_only_failed_destination(): void
    {
        $this->enableRoutines();
        $tablet = $this->device($this->owner, []); // rutinas no requieren capacidad nueva
        $slot = $this->slot();
        $this->push->push(fn ($device) => $device->id === $this->phone->id ? PushResult::accepted('m-1') : PushResult::retryable(503, 'unavailable'),
            fn ($device) => $device->id === $this->phone->id ? PushResult::accepted('m-1') : PushResult::retryable(503, 'unavailable'));
        $this->tick();

        // Mismo formato que el camino legado: hora local de la app (08:00 Guatemala = 14:00 UTC).
        $this->assertSame('2026-09-21 08:00:00', DB::table('focus_slots')->where('id', $slot->id)->value('last_sent_at'));
        $this->at('2026-09-21T14:01:30Z');
        $this->tick();
        $this->assertSame([$this->phone->id, $tablet->id, $tablet->id], array_column($this->push->sent, 'device_id'));
        $data = $this->push->sent[0]['data'];
        $this->assertSame(['type' => 'focus_message', 'slot_id' => (string) $slot->id, 'audio_url' => '', 'title' => 'Cierre', 'body' => 'Mensaje de rutina'], $data);
    }

    public function test_routine_respects_days_window_cutover_and_legacy_sent_occurrences(): void
    {
        $this->enableRoutines('2026-09-21T14:05:00Z');
        $this->slot(); // 08:00 es anterior al corte
        $this->slot(['time' => '08:00', 'days' => [2]]); // martes
        $this->at('2026-09-21T14:10:00Z');
        $this->slot(['time' => '08:09', 'last_sent_at' => now()]); // ya enviada por el camino legado
        $this->tick();
        $this->at('2026-09-21T14:21:00Z');
        $this->slot(['time' => '08:10']); // fuera de la ventana de 10 min
        $this->tick();

        $this->assertSame(0, ReminderDelivery::count());
        $this->assertSame([], $this->push->sent);
    }

    public function test_routine_paused_or_edited_before_job_is_skipped(): void
    {
        Queue::fake();
        $this->enableRoutines();
        $slot = $this->slot();
        $this->tick();
        $slot->update(['enabled' => false]);
        $this->dispatcher()->deliver(ReminderDelivery::sole()->id);
        $this->assertSame([], $this->push->sent);
        $this->assertSame('skipped', ReminderDelivery::sole()->status);
    }

    public function test_legacy_routine_path_is_unchanged_while_flag_is_off(): void
    {
        $this->mock(FcmService::class, fn ($mock) => $mock->shouldReceive('sendToUser')->once()->andReturn(true));
        app(Kernel::class)->registerCommand(new SendFocusMessages(app(FcmService::class), app(TtsService::class)));
        $slot = $this->slot();
        $this->artisan('focus:send-messages')->assertExitCode(0);
        $this->tick();
        $this->assertNotNull($slot->fresh()->last_sent_at);
        $this->assertSame(0, ReminderDelivery::count());
    }
}
