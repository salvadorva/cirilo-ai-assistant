<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\EventNotificationDelivery;
use App\Models\Notification;
use App\Models\User;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\SecurityTestCase;

/** F3: recordatorio previo y aviso de inicio por separado, con estado propio por canal. */
class EventNotificationTest extends SecurityTestCase
{
    private User $owner;

    private int $fcmCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['2025_09_04_110118_create_notifications_table.php', '2026_03_30_103641_add_telegram_fields_to_users_table.php',
            '2026_03_28_095907_add_agenda_reminders_enabled_to_users_table.php'] as $file) {
            $this->artisan('migrate', ['--path' => 'database/migrations/'.$file, '--force' => true])->assertExitCode(0);
        }
        config(['services.n8n.telegram_webhook' => 'https://telegram.example.test/send', 'services.n8n.webhook_secret' => 'fake']);
        $this->owner = $this->user();
        $this->owner->forceFill(['telegram_chat_id' => '123', 'telegram_notifications_enabled' => true, 'agenda_reminders_enabled' => true])->save();
        $this->mock(FcmService::class, fn ($mock) => $mock->shouldReceive('sendToUser')->andReturnUsing(function () {
            $this->fcmCalls++;

            return false; // sin teléfono registrado: no cuenta como enviado
        }));
        Mail::fake();
        $this->at('08:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse("2026-09-21 {$time}:00", 'America/Guatemala'));
    }

    private function cycle(): void
    {
        $this->artisan('agenda:schedule-notifications')->assertExitCode(0);
    }

    private function event(string $start, int $reminder = 30, array $extra = []): CalendarEvent
    {
        return CalendarEvent::create($extra + ['user_id' => $this->owner->id, 'title' => 'Llamada', 'start_date' => "2026-09-21 {$start}",
            'end_date' => Carbon::parse("2026-09-21 {$start}")->addHour(), 'reminder_minutes_before' => $reminder, 'status' => 'pending', 'notified' => false]);
    }

    private function delivery(CalendarEvent $event, string $kind, string $channel): ?EventNotificationDelivery
    {
        return EventNotificationDelivery::where(['calendar_event_id' => $event->id, 'kind' => $kind, 'channel' => $channel])->latest('id')->first();
    }

    /** Un segundo Http::fake se apila detrás del primero; se reemplaza la fábrica para cambiar la respuesta. */
    private function refakeTelegram($response): void
    {
        Http::swap(new Factory);
        Http::fake(['telegram.example.test/*' => $response]);
    }

    private function telegramCalls(): int
    {
        return count(Http::recorded(fn ($request) => str_contains($request->url(), 'telegram.example.test')));
    }

    public function test_reminder_and_start_notice_are_independent(): void
    {
        Http::fake(['telegram.example.test/*' => Http::response(['ok' => true])]);
        $event = $this->event('08:30');

        $this->cycle();
        $this->assertSame('accepted', $this->delivery($event, 'reminder', 'internal')->status);
        $this->assertNull($this->delivery($event, 'start', 'internal'), 'El aviso de inicio aún no está planificado para enviarse.');

        $this->at('08:30');
        $this->cycle();
        $this->assertSame('accepted', $this->delivery($event, 'start', 'internal')->status);
        $this->assertSame(2, Notification::count());
        $this->assertSame(['accepted', 'accepted'], [$this->delivery($event, 'reminder', 'telegram')->status, $this->delivery($event, 'start', 'telegram')->status]);
    }

    public function test_nothing_is_sent_before_its_time_and_late_ones_are_recovered_within_the_window(): void
    {
        Http::fake(['telegram.example.test/*' => Http::response(['ok' => true])]);
        $future = $this->event('08:35');             // recordatorio a las 08:05
        $late = $this->event('08:20');               // recordatorio a las 07:50: 10 min tarde
        $tooLate = $this->event('08:00', 60);        // recordatorio a las 07:00: fuera de la ventana

        $this->cycle();

        $this->assertNull($this->delivery($future, 'reminder', 'internal'));
        $this->assertSame('accepted', $this->delivery($late, 'reminder', 'internal')->status);
        $this->assertSame('skipped', $this->delivery($tooLate, 'reminder', 'internal')->status);
        $this->assertSame('expired', $this->delivery($tooLate, 'reminder', 'internal')->error_category);
        // El recordatorio tardío dice el tiempo real que falta.
        $this->assertStringContainsString('Comienza en 20 minutos', Notification::where('title', 'Recordatorio: Llamada')->value('message'));

        $this->at('08:05');
        $this->cycle();
        $this->assertSame('accepted', $this->delivery($future, 'reminder', 'internal')->status);
    }

    public function test_a_failing_channel_is_retried_without_duplicating_the_others(): void
    {
        Http::fake(['telegram.example.test/*' => Http::sequence()->push([], 503)->push(['ok' => true])]);
        $event = $this->event('08:30');

        $this->cycle();
        $this->assertSame(['retry_wait', 'accepted'], [$this->delivery($event, 'reminder', 'telegram')->status, $this->delivery($event, 'reminder', 'internal')->status]);

        $this->cycle();
        $this->assertSame('accepted', $this->delivery($event, 'reminder', 'telegram')->status);
        $this->assertSame([2, 1], [$this->telegramCalls(), Notification::count()]);
    }

    public function test_retries_are_bounded_and_permanent_errors_are_not_retried(): void
    {
        Http::fake(['telegram.example.test/*' => Http::response([], 503)]);
        $event = $this->event('08:30');

        $this->cycle();                    // intento 1
        $this->cycle();                    // intento 2, en el ciclo siguiente
        $this->cycle();                    // espera creciente: aún no
        $this->at('08:06');
        $this->cycle();                    // intento 3 → agotado
        $this->at('08:15');
        $this->cycle();

        $delivery = $this->delivery($event, 'reminder', 'telegram');
        $this->assertSame(['failed', 'exhausted', 3, 3], [$delivery->status, $delivery->error_category, $delivery->attempts, $this->telegramCalls()]);

        $this->refakeTelegram(Http::response([], 400));
        $other = $this->event('08:45');
        $this->cycle();
        $this->assertSame(['failed', 'rejected', 1], [$this->delivery($other, 'reminder', 'telegram')->status,
            $this->delivery($other, 'reminder', 'telegram')->error_category, $this->delivery($other, 'reminder', 'telegram')->attempts]);
    }

    public function test_cancelling_or_rescheduling_invalidates_pending_notices(): void
    {
        Http::fake(['telegram.example.test/*' => Http::response([], 503)]);
        $cancelled = $this->event('08:30');
        $moved = $this->event('08:30', 30, ['title' => 'Movida']);
        $this->cycle();

        $cancelled->update(['status' => 'cancelled']);
        $moved->update(['start_date' => '2026-09-21 10:00', 'end_date' => '2026-09-21 11:00']);
        $this->refakeTelegram(Http::response(['ok' => true]));
        $this->cycle();

        $this->assertSame(['skipped', 'cancelled'], [$this->delivery($cancelled, 'reminder', 'telegram')->status, $this->delivery($cancelled, 'reminder', 'telegram')->error_category]);
        $old = EventNotificationDelivery::where(['calendar_event_id' => $moved->id, 'channel' => 'telegram'])->oldest('id')->first();
        $this->assertSame(['skipped', 'rescheduled'], [$old->status, $old->error_category]);

        $this->at('09:30');
        $this->cycle();
        $this->assertSame('accepted', $this->delivery($moved, 'reminder', 'telegram')->status, 'El nuevo horario tiene su propio recordatorio.');
    }

    public function test_preferences_and_quiet_hours_are_recorded_as_skipped(): void
    {
        Http::fake();
        $this->owner->forceFill(['telegram_chat_id' => null, 'agenda_reminders_enabled' => false])->save();
        $event = $this->event('08:30');

        $this->cycle();

        $this->assertSame(['skipped', 'preference'], [$this->delivery($event, 'reminder', 'telegram')->status, $this->delivery($event, 'reminder', 'telegram')->error_category]);
        $this->assertSame('preference', $this->delivery($event, 'reminder', 'email')->error_category);
        $this->assertSame('accepted', $this->delivery($event, 'reminder', 'internal')->status);

        $this->owner->forceFill(['agenda_reminders_enabled' => true])->save();
        $this->at('19:00');
        $night = $this->event('19:30');
        $this->cycle();
        $this->assertSame(['skipped', 'quiet_hours'], [$this->delivery($night, 'reminder', 'email')->status, $this->delivery($night, 'reminder', 'email')->error_category]);
    }

    public function test_email_is_accepted_when_the_mailer_accepts_it(): void
    {
        Http::fake(['telegram.example.test/*' => Http::response(['ok' => true])]);
        $event = $this->event('08:30');

        $this->cycle();

        $this->assertSame('accepted', $this->delivery($event, 'reminder', 'email')->status);
    }

    public function test_legacy_notified_events_are_not_resent_but_future_notices_still_go_out(): void
    {
        Http::fake(['telegram.example.test/*' => Http::response(['ok' => true])]);
        // Antes del corte, el comando viejo ya mandó el recordatorio de las 07:55 y marcó notified.
        $event = $this->event('08:25', 30, ['notified' => true]);

        $this->cycle();
        $this->assertSame(['skipped', 'legacy_notified'], [$this->delivery($event, 'reminder', 'internal')->status, $this->delivery($event, 'reminder', 'internal')->error_category]);
        $this->assertSame(0, Notification::count());

        $this->at('08:25');
        $this->cycle();
        $this->assertSame('accepted', $this->delivery($event, 'start', 'internal')->status);
    }

    public function test_an_expired_claim_on_a_provider_without_idempotency_is_not_resent(): void
    {
        Http::fake(['telegram.example.test/*' => Http::response(['ok' => true])]);
        $event = $this->event('08:30');
        $this->cycle();
        $delivery = $this->delivery($event, 'reminder', 'telegram');
        // Simula un proceso que murió después de llamar a Telegram y antes de guardar el resultado.
        $delivery->update(['status' => 'processing', 'claim_token' => 'muerto', 'lease_expires_at' => now()->subMinute()]);

        $this->cycle();

        $this->assertSame(['uncertain', 1], [$delivery->fresh()->status, $this->telegramCalls()]);
    }

    public function test_event_detail_shows_provider_acceptance_not_reception(): void
    {
        Http::fake(['telegram.example.test/*' => Http::response(['ok' => true])]);
        $event = $this->event('08:30');
        $this->cycle();

        $notices = $this->actingAs($this->owner)->getJson("/agenda/events/{$event->id}")->assertOk()->json('event.notifications');

        $telegram = collect($notices)->firstWhere('channel', 'telegram');
        $this->assertSame(['reminder', 'accepted', 'Aceptado por el proveedor'], [$telegram['kind'], $telegram['status'], $telegram['label']]);
        $this->assertStringNotContainsString('eído', json_encode($notices, JSON_UNESCAPED_UNICODE), 'No se afirma lectura sin evidencia.');
    }
}
