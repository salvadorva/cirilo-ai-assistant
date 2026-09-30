<?php

namespace Tests\Feature;

use App\Models\ContextualReminder;
use App\Models\DeviceInstallationClaim;
use App\Models\DeviceToken;
use App\Models\ReminderDelivery;
use App\Models\ReminderIntegration;
use App\Models\User;
use App\Services\FcmService;
use App\Services\Reminders\Push\PushTransport;
use App\Services\Reminders\ReminderDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fakes\FakePushTransport;
use Tests\SecurityTestCase;

/** Reclamo explícito de una instalación en conflicto (hallazgo Android: cierre de sesión sin red). */
class DeviceInstallationClaimTest extends SecurityTestCase
{
    private const INSTALLATION = '0f8c2b1e-7a4d-4a5e-9b61-3c2d1e0f9a8b';

    private const FCM = 'fcm-token-del-telefono-0001';

    private User $previous;

    private User $claimer;

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
            '2026_09_23_130000_create_device_installation_claims_table.php',
        ]), '--force' => true])->assertExitCode(0);
        Carbon::setTestNow(Carbon::parse('2026-09-21T14:00:00Z')->setTimezone(config('app.timezone')));
        $this->push = new FakePushTransport;
        $this->app->instance(PushTransport::class, $this->push);
        $this->mock(FcmService::class, fn ($mock) => $mock->shouldNotReceive('sendToUser'));
        config(['reminders.mobile_api_enabled' => true, 'reminders.hermes_api_enabled' => true, 'reminders.contextual_dispatch_enabled' => true]);

        $this->previous = $this->user();
        $this->claimer = $this->user();
        // El dueño anterior registró el teléfono y cerró sesión sin red: el DELETE nunca llegó.
        $this->mobile('POST', '/api/mobile/device-token', $this->registration(), $this->previous)->assertOk();
        config(['reminders.dispatch_device_allowlist' => [DeviceToken::sole()->id]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function registration(array $overrides = []): array
    {
        return $overrides + ['token' => self::FCM, 'platform' => 'android', 'installation_id' => self::INSTALLATION,
            'capabilities' => ['contextual_reminders_v1'], 'app_version' => '1.4.0'];
    }

    private function mobile(string $method, string $uri, array $data, ?User $user, array $headers = [])
    {
        $auth = $user ? ['Authorization' => 'Bearer '.$user->createToken('android')->plainTextToken] : [];
        $response = $this->flushHeaders()->withHeaders($auth + $headers)->json($method, $uri, $data);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function claim(array $overrides = [], ?User $user = null)
    {
        return $this->mobile('POST', '/api/mobile/device-token/claim', $this->registration($overrides), $user ?? $this->claimer);
    }

    private function dueReminderFor(User $user): ContextualReminder
    {
        return ContextualReminder::create(['user_id' => $user->id, 'title' => 'T', 'context' => 'CONTEXTO_PRIVADO', 'next_action' => 'A',
            'scheduled_at' => '2026-09-21 14:00:00', 'expires_at' => '2026-09-21 16:00:00', 'timezone' => 'America/Guatemala',
            'state' => 'pending', 'version' => 1, 'confirmed_by_user' => true, 'confirmed_at' => '2026-09-21 13:00:00']);
    }

    public function test_registration_still_never_transfers_silently(): void
    {
        $this->mobile('POST', '/api/mobile/device-token', $this->registration(), $this->claimer)
            ->assertStatus(409)->assertJsonPath('code', 'installation_conflict');
        $this->assertSame($this->previous->id, DeviceToken::sole()->user_id);
        $this->assertSame(0, DeviceInstallationClaim::count());
    }

    public function test_claim_with_proof_of_possession_transfers_installation_and_invalidates_previous_owner(): void
    {
        Queue::fake();
        $old = $this->dueReminderFor($this->previous);
        $this->artisan('reminders:dispatch')->assertExitCode(0); // despacho pendiente del dueño anterior hacia este teléfono
        $pending = ReminderDelivery::sole();

        $this->claim()->assertOk()
            ->assertJsonPath('claimed', true)->assertJsonPath('installation_id', self::INSTALLATION)
            ->assertJsonPath('capabilities', ['contextual_reminders_v1']);

        $device = DeviceToken::sole();
        $this->assertSame($this->claimer->id, $device->user_id);
        $this->assertSame(['skipped', 'installation_transferred'], [$pending->fresh()->status, $pending->fresh()->error_category]);

        // Un job viejo ya encolado tampoco envía al nuevo dueño contenido del anterior.
        ReminderDelivery::whereKey($pending->id)->update(['status' => 'pending', 'error_category' => null]);
        app(ReminderDispatcher::class)->deliver($pending->id);
        $this->assertSame([], $this->push->sent);

        // El dueño anterior ya no tiene destino ni puede enviar recibos con esa instalación.
        Carbon::setTestNow(now()->addMinute());
        $this->artisan('reminders:dispatch')->assertExitCode(0);
        $this->assertSame(1, ReminderDelivery::count());
        $this->mobile('POST', "/api/mobile/contextual-reminders/{$old->id}/receipts",
            ['event' => 'displayed', 'version' => 1, 'installation_id' => self::INSTALLATION], $this->previous)
            ->assertStatus(422)->assertJsonPath('code', 'unknown_installation');
        // El nuevo dueño no ve recordatorios del anterior.
        $this->mobile('GET', "/api/mobile/contextual-reminders/{$old->id}", [], $this->claimer)->assertNotFound();
        $this->mobile('POST', '/api/mobile/device-token', $this->registration(), $this->previous)->assertStatus(409);

        $audit = DeviceInstallationClaim::sole();
        $this->assertSame(['transferred', $this->previous->id, $this->claimer->id, $device->id],
            [$audit->outcome, $audit->from_user_id, $audit->to_user_id, $audit->device_token_id]);
        $this->assertStringNotContainsString(self::FCM, json_encode($audit->toArray()));
    }

    public function test_claim_is_idempotent_for_the_current_owner(): void
    {
        $this->claim()->assertOk()->assertJsonPath('claimed', true);
        $this->claim()->assertOk()->assertJsonPath('claimed', false)->assertJsonPath('already_owned', true);
        $this->assertSame($this->claimer->id, DeviceToken::sole()->user_id);
        $this->assertSame(['transferred', 'already_owned'], DeviceInstallationClaim::orderBy('id')->pluck('outcome')->all());
    }

    public function test_claim_without_the_current_fcm_token_is_denied_and_audited(): void
    {
        $this->claim(['token' => 'token-que-no-es-del-telefono'])->assertStatus(403)->assertJsonPath('code', 'possession_not_proven');
        $this->assertSame($this->previous->id, DeviceToken::sole()->user_id);
        $this->assertSame(self::FCM, DeviceToken::sole()->token);
        $this->assertSame('denied_possession', DeviceInstallationClaim::sole()->outcome);
    }

    public function test_unknown_installation_is_not_claimable(): void
    {
        $this->claim(['installation_id' => (string) Str::uuid()])->assertNotFound()->assertJsonPath('code', 'installation_not_found');
        $this->claim(['installation_id' => 'x'])->assertStatus(422);
        $this->claim(['capabilities' => 'contextual_reminders_v1'])->assertStatus(422);
    }

    public function test_claims_are_rate_limited_per_user_and_per_installation(): void
    {
        config(['reminders.claims.attempts_per_hour' => 3]);
        foreach (range(1, 3) as $i) {
            $this->claim(['token' => 'mala-'.$i])->assertStatus(403);
        }
        $this->claim()->assertStatus(429)->assertJsonPath('code', 'rate_limited')->assertHeader('Retry-After');
        // Otra cuenta tampoco puede seguir probando contra la misma instalación.
        $this->claim(['token' => 'mala-4'], $this->user())->assertStatus(429);
        $this->assertSame($this->previous->id, DeviceToken::sole()->user_id);
        $this->assertSame(3, DeviceInstallationClaim::where('outcome', 'denied_possession')->count());
    }

    public function test_claim_requires_a_sanctum_user_not_hermes_or_anonymous(): void
    {
        $this->mobile('POST', '/api/mobile/device-token/claim', $this->registration(), null)->assertUnauthorized();
        [, $hermes] = ReminderIntegration::issue($this->claimer, 'hermes');
        $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$hermes])->postJson('/api/mobile/device-token/claim', $this->registration())
            ->assertUnauthorized();
        $this->assertSame($this->previous->id, DeviceToken::sole()->user_id);
    }

    public function test_operator_release_for_rotated_token_dead_end_is_audited(): void
    {
        $this->assertSame(0, Artisan::call('reminders:installation-release', ['installation' => self::INSTALLATION, '--reason' => 'soporte: token rotado']));
        $this->assertSame(0, DeviceToken::count());
        $this->assertSame(['operator_release', $this->previous->id, null], [DeviceInstallationClaim::sole()->outcome,
            DeviceInstallationClaim::sole()->from_user_id, DeviceInstallationClaim::sole()->to_user_id]);
        $this->mobile('POST', '/api/mobile/device-token', $this->registration(['token' => 'token-rotado']), $this->claimer)->assertOk();
    }

    // ---- Hallazgo (b): minimizar contexto en respuestas ----

    public function test_list_does_not_expose_context_or_next_action_only_detail_does(): void
    {
        $reminder = $this->dueReminderFor($this->previous);
        $list = $this->mobile('GET', '/api/mobile/contextual-reminders', [], $this->previous)->assertOk()->json('data.0');
        $this->assertSame($reminder->id, $list['id']);
        $this->assertSame('T', $list['title']);
        $this->assertArrayNotHasKey('context', $list);
        $this->assertArrayNotHasKey('next_action', $list);
        $this->assertStringNotContainsString('CONTEXTO_PRIVADO', json_encode($list));
        $this->mobile('GET', "/api/mobile/contextual-reminders/{$reminder->id}", [], $this->previous)->assertJsonPath('context', 'CONTEXTO_PRIVADO');
    }
}
