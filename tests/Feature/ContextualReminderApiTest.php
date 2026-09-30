<?php

namespace Tests\Feature;

use App\Models\ContextualReminder;
use App\Models\DeviceToken;
use App\Models\ReminderCommand;
use App\Models\ReminderIntegration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\SecurityTestCase;

/** RC1: dominio y API local de recordatorios contextuales, sin despacho ni FCM. */
class ContextualReminderApiTest extends SecurityTestCase
{
    private const BASE = '/api/integrations/hermes/v1/reminders';

    private User $owner;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => ['database/migrations/2026_05_22_101002_create_device_tokens_table.php',
            'database/migrations/2026_09_23_100000_create_contextual_reminders_tables.php',
            'database/migrations/2026_09_23_110000_add_reminder_dispatch.php', 'database/migrations/2026_09_23_120000_add_reminder_device_receipts.php'], '--force' => true])->assertExitCode(0);
        config(['reminders.hermes_api_enabled' => true, 'reminders.mobile_api_enabled' => true]);
        // 08:00 en Guatemala = 14:00 UTC.
        Carbon::setTestNow(Carbon::parse('2026-09-21T14:00:00Z'));
        $this->owner = $this->user();
        [, $this->token] = ReminderIntegration::issue($this->owner, 'hermes');
        $this->readyDevice($this->owner);
    }

    /** Teléfono compatible y seleccionado: crear exige destino listo desde RC2. */
    private function readyDevice(User $user): void
    {
        $device = DeviceToken::create(['user_id' => $user->id, 'token' => 'fcm-'.Str::random(40), 'capabilities' => ['contextual_reminders_v1']]);
        config(['reminders.dispatch_device_allowlist' => array_merge(config('reminders.dispatch_device_allowlist', []), [$device->id])]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Enviar propuesta a Ana',
            'context' => 'Acordamos cerrar la propuesta antes de la reunión del jueves.',
            'next_action' => 'Abrir el borrador y enviarlo por correo.',
            'scheduled_at' => '2026-09-21T08:30:00-06:00',
            'expires_at' => '2026-09-21T10:00:00-06:00',
            'timezone' => 'America/Guatemala',
            'with_audio' => false,
            'confirmed_by_user' => true,
            'confirmed_at' => '2026-09-21T07:59:00-06:00',
        ], $overrides);
    }

    private function hermes(string $method, string $uri, array $data = [], ?string $key = null, ?string $token = null)
    {
        $headers = ['Authorization' => 'Bearer '.($token ?? $this->token)];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $this->flushHeaders()->withHeaders($headers)->json($method, $uri, $data);
    }

    private function create(array $overrides = [], ?string $key = null)
    {
        return $this->hermes('POST', self::BASE, $this->payload($overrides), $key ?? (string) Str::uuid());
    }

    private function mobile(string $method, string $uri, array $data = [], ?string $key = null, ?User $user = null)
    {
        $token = ($user ?? $this->owner)->createToken('android')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }
        $response = $this->flushHeaders()->withHeaders($headers)->json($method, $uri, $data);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    // ---- Contrato de creación y tiempo ----

    public function test_create_persists_pending_reminder_in_utc_and_returns_minimal_confirmation(): void
    {
        $response = $this->create()->assertCreated();

        $id = $response->json('id');
        $response->assertHeader('Location', url(self::BASE.'/'.$id))
            ->assertJsonPath('occurrence_id', $id)
            ->assertJsonPath('state', 'pending')
            ->assertJsonPath('version', 1)
            ->assertJsonPath('scheduled_at', '2026-09-21T14:30:00Z')
            ->assertJsonPath('expires_at', '2026-09-21T16:00:00Z')
            ->assertJsonPath('dispatch.state', 'not_started')
            ->assertJsonPath('server_time', '2026-09-21T14:00:00Z');
        $this->assertArrayNotHasKey('context', $response->json());
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));

        $reminder = ContextualReminder::findOrFail($id);
        $this->assertSame($this->owner->id, $reminder->user_id);
        $this->assertSame('2026-09-21 14:30:00', $reminder->getRawOriginal('scheduled_at'));
        $this->assertSame('2026-09-21T14:30:00+00:00', $reminder->scheduled_at->toIso8601String());
        $this->assertTrue($reminder->confirmed_by_user);
        Http::assertNothingSent();
    }

    public function test_owner_and_integration_come_from_authentication_not_body(): void
    {
        $other = $this->user();
        $this->create(['user_id' => $other->id])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        $this->create(['state' => 'completed'])->assertStatus(422);
        $this->create(['recipient' => 'x'])->assertStatus(422);
        $this->create(['recurrence' => 'daily'])->assertStatus(422);
        $this->assertSame(0, ContextualReminder::count());
    }

    /** @return array<string, array{0: array}> */
    public static function invalidPayloads(): array
    {
        return [
            'sin contexto' => [['context' => '']],
            'sin siguiente acción' => [['next_action' => null]],
            'título largo' => [['title' => str_repeat('a', 121)]],
            'contexto largo' => [['context' => str_repeat('a', 501)]],
            'acción larga' => [['next_action' => str_repeat('a', 301)]],
            'html' => [['title' => '<b>Enviar</b>']],
            'sin offset' => [['scheduled_at' => '2026-09-21T08:30:00']],
            'lenguaje natural' => [['scheduled_at' => 'hoy a las 8:30']],
            'offset distinto a zona' => [['scheduled_at' => '2026-09-21T09:30:00-05:00']],
            'zona inválida' => [['timezone' => 'Guatemala/Centro']],
            'pasado' => [['scheduled_at' => '2026-09-21T07:00:00-06:00']],
            'menos de un minuto' => [['scheduled_at' => '2026-09-21T08:00:30-06:00']],
            'más de siete días' => [['scheduled_at' => '2026-09-28T08:30:00-06:00', 'expires_at' => '2026-09-28T09:30:00-06:00']],
            'caducidad antes' => [['expires_at' => '2026-09-21T08:30:00-06:00']],
            'ventana mayor a 4h' => [['expires_at' => '2026-09-21T12:31:00-06:00']],
            'sin confirmación' => [['confirmed_by_user' => false]],
            'confirmación futura' => [['confirmed_at' => '2026-09-21T09:00:00-06:00']],
            'voz no permitida' => [['voice' => 'robot']],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_are_rejected_without_persisting(array $overrides): void
    {
        $this->create($overrides)->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['code', 'message', 'request_id', 'errors']);
        $this->assertSame(0, ContextualReminder::count());
        $this->assertSame(0, ReminderCommand::count());
    }

    public function test_audio_is_explicitly_rejected_until_enabled(): void
    {
        $this->create(['with_audio' => true])->assertStatus(422)->assertJsonPath('code', 'audio_not_available');
        $this->assertSame(0, ContextualReminder::count());
    }

    public function test_idempotency_key_is_required_for_mutations(): void
    {
        $this->hermes('POST', self::BASE, $this->payload())->assertStatus(422)->assertJsonPath('code', 'idempotency_key_required');
        $this->hermes('POST', self::BASE, $this->payload(), 'corta')->assertStatus(422)->assertJsonPath('code', 'idempotency_key_required');
        $this->assertSame(0, ContextualReminder::count());
    }

    // ---- Idempotencia ----

    public function test_replay_returns_original_result_without_new_effects(): void
    {
        $key = (string) Str::uuid();
        $first = $this->create([], $key)->assertCreated();
        $replay = $this->create([], $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('id'), $replay->json('id'));
        $this->assertSame(1, ContextualReminder::count());
        $this->assertSame(1, ReminderCommand::count());
    }

    public function test_same_key_with_different_body_is_a_conflict(): void
    {
        $key = (string) Str::uuid();
        $this->create([], $key)->assertCreated();
        $this->create(['title' => 'Otra cosa'], $key)->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');
        $this->assertSame(1, ContextualReminder::count());
    }

    public function test_key_reserved_by_an_unfinished_request_is_not_executed_twice(): void
    {
        $key = (string) Str::uuid();
        $integration = ReminderIntegration::first();
        ReminderCommand::create(['actor_type' => 'integration', 'actor_id' => $integration->id, 'idempotency_key' => $key,
            'operation' => 'create', 'request_hash' => str_repeat('0', 64)]);

        $this->create([], $key)->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');
        $this->assertSame(0, ContextualReminder::count());
    }

    public function test_losing_the_unique_reservation_race_never_executes_twice(): void
    {
        $key = (string) Str::uuid();
        $integration = ReminderIntegration::first();
        // Simula que otra petición reserva la misma clave entre la búsqueda y el INSERT.
        ReminderCommand::creating(function () use ($integration, $key) {
            ReminderCommand::flushEventListeners();
            ReminderCommand::create(['actor_type' => 'integration', 'actor_id' => $integration->id, 'idempotency_key' => $key,
                'operation' => 'create', 'request_hash' => str_repeat('0', 64)]);
        });

        $this->create([], $key)->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');
        $this->assertSame(0, ContextualReminder::count());
    }

    public function test_replay_survives_credential_rotation(): void
    {
        $key = (string) Str::uuid();
        $first = $this->create([], $key)->assertCreated();
        $newToken = ReminderIntegration::first()->rotate();

        $this->hermes('POST', self::BASE, $this->payload(), $key, $this->token)->assertUnauthorized();
        $this->hermes('POST', self::BASE, $this->payload(), $key, $newToken)->assertCreated()
            ->assertJsonPath('id', $first->json('id'))->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, ContextualReminder::count());
    }

    public function test_replay_does_not_consume_business_quota(): void
    {
        config(['reminders.max_pending' => 1]);
        $key = (string) Str::uuid();
        $this->create([], $key)->assertCreated();
        $this->create([], $key)->assertCreated();
        $this->create()->assertStatus(429)->assertJsonPath('code', 'pending_limit_reached')->assertHeader('Retry-After');
    }

    public function test_daily_creation_quota_counts_closed_reminders_too(): void
    {
        config(['reminders.max_daily_creates' => 2]);
        foreach ([1, 2] as $i) {
            $id = $this->create()->assertCreated()->json('id');
            $this->hermes('POST', self::BASE."/{$id}/cancel", ['expected_version' => 1], (string) Str::uuid())->assertOk();
        }
        $this->create()->assertStatus(429)->assertJsonPath('code', 'daily_limit_reached');
    }

    // ---- Estados, versiones y carreras ----

    public function test_complete_cancel_are_terminal_and_versioned(): void
    {
        $id = $this->create()->json('id');
        $this->hermes('POST', self::BASE."/{$id}/complete", ['expected_version' => 1], (string) Str::uuid())
            ->assertOk()->assertJsonPath('state', 'completed')->assertJsonPath('version', 2);

        $this->hermes('POST', self::BASE."/{$id}/cancel", ['expected_version' => 2], (string) Str::uuid())
            ->assertStatus(409)->assertJsonPath('code', 'invalid_state')->assertJsonPath('current.state', 'completed');
        $this->hermes('POST', self::BASE."/{$id}/snooze", ['expected_version' => 2, 'minutes' => 15], (string) Str::uuid())
            ->assertStatus(409)->assertJsonPath('code', 'invalid_state');
        $this->assertNotNull(ContextualReminder::find($id)->completed_at);
    }

    public function test_stale_version_loses_race_and_gets_current_state(): void
    {
        $id = $this->create()->json('id');
        $this->hermes('POST', self::BASE."/{$id}/snooze", ['expected_version' => 1, 'minutes' => 30], (string) Str::uuid())->assertOk();
        // Otra acción basada en la versión anterior pierde.
        $this->hermes('POST', self::BASE."/{$id}/complete", ['expected_version' => 1], (string) Str::uuid())
            ->assertStatus(409)->assertJsonPath('code', 'version_conflict')
            ->assertJsonPath('current.version', 2)->assertJsonPath('current.state', 'pending');
    }

    public function test_snooze_moves_same_occurrence_from_server_time_and_replay_does_not_add_minutes(): void
    {
        $id = $this->create()->json('id');
        Carbon::setTestNow(Carbon::parse('2026-09-21T14:31:00Z'));
        $key = (string) Str::uuid();
        $this->hermes('POST', self::BASE."/{$id}/snooze", ['expected_version' => 1, 'minutes' => 15], $key)
            ->assertOk()->assertJsonPath('id', $id)->assertJsonPath('version', 2)
            ->assertJsonPath('scheduled_at', '2026-09-21T14:46:00Z')->assertJsonPath('expires_at', '2026-09-21T16:00:00Z');
        Carbon::setTestNow(Carbon::parse('2026-09-21T14:40:00Z'));
        $this->hermes('POST', self::BASE."/{$id}/snooze", ['expected_version' => 1, 'minutes' => 15], $key)
            ->assertOk()->assertJsonPath('scheduled_at', '2026-09-21T14:46:00Z')->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, ContextualReminder::count());
        $this->assertSame(2, ContextualReminder::find($id)->version);
    }

    public function test_snooze_rules(): void
    {
        $id = $this->create()->json('id');
        $snooze = fn (array $body) => $this->hermes('POST', self::BASE."/{$id}/snooze", $body + ['expected_version' => 1], (string) Str::uuid());

        $snooze(['minutes' => 20])->assertStatus(422);
        $snooze(['minutes' => 15, 'scheduled_at' => '2026-09-21T09:00:00-06:00'])->assertStatus(422);
        $snooze([])->assertStatus(422);
        Carbon::setTestNow(Carbon::parse('2026-09-21T15:30:00Z'));
        $snooze(['minutes' => 30])->assertStatus(409)->assertJsonPath('code', 'snooze_exceeds_expiry');
        $snooze(['scheduled_at' => '2026-09-21T10:45:00-05:00'])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        $snooze(['scheduled_at' => '2026-09-21T09:45:00-06:00'])->assertOk()->assertJsonPath('scheduled_at', '2026-09-21T15:45:00Z');
    }

    public function test_expiration_is_enforced_inside_operations_without_scheduler_tick(): void
    {
        $id = $this->create()->json('id');
        Carbon::setTestNow(Carbon::parse('2026-09-21T16:00:00Z'));

        $this->hermes('GET', self::BASE."/{$id}")->assertOk()->assertJsonPath('state', 'expired');
        $this->hermes('GET', self::BASE.'?state=pending')->assertOk()->assertJsonCount(0, 'data');
        $this->hermes('POST', self::BASE."/{$id}/complete", ['expected_version' => 1], (string) Str::uuid())
            ->assertStatus(409)->assertJsonPath('code', 'reminder_expired')->assertJsonPath('current.state', 'expired');

        $reminder = ContextualReminder::find($id);
        $this->assertSame('expired', $reminder->state);
        $this->assertSame(2, $reminder->version);
        $this->assertNotNull($reminder->expired_at);
    }

    // ---- Lectura ----

    public function test_list_and_detail_only_show_owned_reminders_ordered_and_paginated(): void
    {
        $late = $this->create(['scheduled_at' => '2026-09-21T09:00:00-06:00'])->json('id');
        $early = $this->create()->json('id');
        $stranger = $this->user();
        $this->readyDevice($stranger);
        [, $otherToken] = ReminderIntegration::issue($stranger, 'hermes-otro');
        $foreign = $this->hermes('POST', self::BASE, $this->payload(), (string) Str::uuid(), $otherToken)->json('id');

        $this->hermes('GET', self::BASE.'?state=pending&per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $early)->assertJsonPath('meta.total', 2);
        $this->hermes('GET', self::BASE."/{$late}")->assertOk()
            ->assertJsonPath('context', 'Acordamos cerrar la propuesta antes de la reunión del jueves.')
            ->assertJsonPath('next_action', 'Abrir el borrador y enviarlo por correo.');
        $this->hermes('GET', self::BASE."/{$foreign}")->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->hermes('POST', self::BASE."/{$foreign}/cancel", ['expected_version' => 1], (string) Str::uuid())->assertNotFound();
        $this->hermes('GET', self::BASE.'?state=todo')->assertStatus(422);
    }

    // ---- Seguridad de la credencial ----

    public function test_credential_is_stored_hashed_and_rejected_when_missing_revoked_or_expired(): void
    {
        $integration = ReminderIntegration::first();
        $this->assertNotSame($this->token, $integration->token_hash);
        $this->assertStringNotContainsString($this->token, json_encode($integration->toArray()));

        $this->flushHeaders()->json('GET', self::BASE)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->hermes('GET', self::BASE, token: 'cirilo_hrm_invalido')->assertUnauthorized();

        $integration->update(['expires_at' => now()->subMinute()]);
        $this->hermes('GET', self::BASE)->assertUnauthorized();
        $integration->update(['expires_at' => null, 'revoked_at' => now()]);
        $this->hermes('GET', self::BASE)->assertUnauthorized();
    }

    public function test_scopes_are_enforced_per_operation(): void
    {
        [, $readOnly] = ReminderIntegration::issue($this->owner, 'lectura', ['reminders:read']);
        $this->hermes('GET', self::BASE, token: $readOnly)->assertOk();
        $this->hermes('POST', self::BASE, $this->payload(), (string) Str::uuid(), $readOnly)
            ->assertForbidden()->assertJsonPath('code', 'insufficient_scope');
    }

    public function test_hermes_credential_is_not_accepted_by_mobile_web_or_chat_routes(): void
    {
        foreach (['/api/mobile/me', '/api/mobile/focus-slots', '/api/mobile/agenda/events', '/api/mobile/memory', '/api/mobile/contextual-reminders'] as $uri) {
            $this->hermes('GET', $uri)->assertUnauthorized();
        }
        $this->hermes('POST', '/api/mobile/chat', ['prompt' => 'Hola'])->assertUnauthorized();
        $this->hermes('POST', '/api/mobile/device-token', ['token' => 'x'])->assertUnauthorized();
        $this->hermes('POST', '/generate-text', ['prompt' => 'Hola'])->assertUnauthorized();
    }

    public function test_mobile_token_is_not_accepted_by_hermes_api(): void
    {
        $this->mobile('GET', self::BASE)->assertUnauthorized();
        $this->mobile('POST', self::BASE, $this->payload(), (string) Str::uuid())->assertUnauthorized();
        $this->assertSame(0, ContextualReminder::count());
    }

    public function test_failed_authentication_is_rate_limited_by_ip(): void
    {
        config(['reminders.rate_limits.auth_failures_per_minute' => 2]);
        $this->hermes('GET', self::BASE, token: 'malo-1')->assertUnauthorized();
        $this->hermes('GET', self::BASE, token: 'malo-2')->assertUnauthorized();
        $this->hermes('GET', self::BASE, token: 'malo-3')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_integration_mutation_rate_limit(): void
    {
        config(['reminders.rate_limits.mutations_per_minute' => 1]);
        $this->create()->assertCreated();
        $this->create()->assertStatus(429)->assertJsonPath('code', 'rate_limited')->assertHeader('Retry-After');
    }

    public function test_feature_flags_close_each_api_independently(): void
    {
        config(['reminders.hermes_api_enabled' => false]);
        $this->create()->assertStatus(503)->assertJsonPath('code', 'feature_disabled');
        $this->assertSame(0, ContextualReminder::count());

        config(['reminders.hermes_api_enabled' => true, 'reminders.mobile_api_enabled' => false]);
        $this->mobile('GET', '/api/mobile/contextual-reminders')->assertStatus(503);
    }

    public function test_defaults_keep_both_apis_disabled(): void
    {
        $defaults = require config_path('reminders.php');
        $this->assertFalse($defaults['hermes_api_enabled']);
        $this->assertFalse($defaults['mobile_api_enabled']);
        $this->assertFalse($defaults['audio_enabled']);
    }

    public function test_context_is_not_written_to_logs(): void
    {
        $id = $this->create()->json('id');
        $this->hermes('POST', self::BASE."/{$id}/cancel", ['expected_version' => 1], (string) Str::uuid())->assertOk();
        foreach ($this->aiLogs->getRecords() as $record) {
            $this->assertStringNotContainsString('Acordamos cerrar', json_encode($record->toArray()));
        }
        $this->assertStringNotContainsString('Acordamos cerrar', json_encode(ReminderCommand::all()->toArray()));
    }

    // ---- API móvil equivalente ----

    public function test_mobile_owner_can_list_view_and_act_with_same_rules(): void
    {
        $id = $this->create()->json('id');

        $this->mobile('GET', '/api/mobile/contextual-reminders')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}")->assertOk()->assertJsonPath('next_action', 'Abrir el borrador y enviarlo por correo.');
        $this->mobile('POST', "/api/mobile/contextual-reminders/{$id}/snooze", ['expected_version' => 1, 'minutes' => 60])->assertStatus(422)
            ->assertJsonPath('code', 'idempotency_key_required');

        $key = (string) Str::uuid();
        $this->mobile('POST', "/api/mobile/contextual-reminders/{$id}/complete", ['expected_version' => 1], $key)->assertOk()->assertJsonPath('state', 'completed');
        $this->mobile('POST', "/api/mobile/contextual-reminders/{$id}/complete", ['expected_version' => 1], $key)->assertOk()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(2, ContextualReminder::find($id)->version);
    }

    public function test_mobile_user_cannot_see_or_act_on_foreign_reminders_or_create(): void
    {
        $id = $this->create()->json('id');
        $stranger = $this->user();

        $this->mobile('GET', '/api/mobile/contextual-reminders', user: $stranger)->assertOk()->assertJsonCount(0, 'data');
        $this->mobile('GET', "/api/mobile/contextual-reminders/{$id}", user: $stranger)->assertNotFound();
        $this->mobile('POST', "/api/mobile/contextual-reminders/{$id}/cancel", ['expected_version' => 1], (string) Str::uuid(), $stranger)->assertNotFound();
        $this->mobile('POST', '/api/mobile/contextual-reminders', $this->payload(), (string) Str::uuid())->assertStatus(405);
        $this->assertSame('pending', ContextualReminder::find($id)->state);
    }

    public function test_idempotency_scope_is_per_actor(): void
    {
        $id = $this->create()->json('id');
        $key = (string) Str::uuid();
        $this->hermes('POST', self::BASE."/{$id}/snooze", ['expected_version' => 1, 'minutes' => 15], $key)->assertOk();
        // La misma clave en otro actor es otra operación y compite por versión.
        $this->mobile('POST', "/api/mobile/contextual-reminders/{$id}/snooze", ['expected_version' => 1, 'minutes' => 15], $key)
            ->assertStatus(409)->assertJsonPath('code', 'version_conflict');
    }
}
