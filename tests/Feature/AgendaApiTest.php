<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Agenda\AgendaDateRange;
use Carbon\Carbon;
use Tests\SecurityTestCase;

/** F2-01/F2-03/F2-05: web y móvil comparten validación, propiedad y persistencia. */
class AgendaApiTest extends SecurityTestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-21 08:00:00', 'America/Guatemala'));
        $this->owner = $this->user();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mobile(string $method, string $uri, array $data = [], array $headers = [], ?User $user = null)
    {
        $response = $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.($user ?? $this->owner)->createToken('android')->plainTextToken] + $headers)
            ->json($method, $uri, $data);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function web(string $method, string $uri, array $data = [])
    {
        $response = $this->flushHeaders()->actingAs($this->owner)->json($method, $uri, $data);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    public function test_mobile_create_is_idempotent_with_a_key(): void
    {
        $payload = ['title' => 'Dentista', 'start_date' => '2026-09-22T10:00:00-06:00', 'end_date' => '2026-09-22T11:00:00-06:00'];

        $first = $this->mobile('POST', '/api/mobile/agenda/events', $payload, ['Idempotency-Key' => 'mob-1'])->assertCreated();
        $second = $this->mobile('POST', '/api/mobile/agenda/events', $payload, ['Idempotency-Key' => 'mob-1'])->assertOk()->assertHeader('Idempotent-Replay', 'true');

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, CalendarEvent::count());
        $this->mobile('POST', '/api/mobile/agenda/events', ['title' => 'Otro'] + $payload, ['Idempotency-Key' => 'mob-1'])->assertStatus(409);
    }

    public function test_mobile_times_keep_the_offset_the_phone_sent(): void
    {
        $this->mobile('POST', '/api/mobile/agenda/events', ['title' => 'Dentista', 'start_date' => '2026-09-22T16:00:00Z'])->assertCreated();

        $this->assertSame('2026-09-22 10:00', CalendarEvent::sole()->start_date->format('Y-m-d H:i'), '16:00 UTC son las 10:00 en Guatemala.');
    }

    public function test_end_before_start_is_rejected_on_web_and_mobile(): void
    {
        $this->mobile('POST', '/api/mobile/agenda/events', ['title' => 'X', 'start_date' => '2026-09-22T10:00:00-06:00', 'end_date' => '2026-09-22T09:00:00-06:00'])
            ->assertStatus(422);
        $this->web('POST', '/agenda/events', ['title' => 'X', 'start_date' => '2026-09-22', 'start_time' => '10:00', 'end_time' => '09:00'])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(0, CalendarEvent::count());
    }

    public function test_rescheduling_resets_the_reminder_on_web_and_mobile(): void
    {
        $event = CalendarEvent::create(['user_id' => $this->owner->id, 'title' => 'Cita', 'start_date' => '2026-09-22 10:00', 'end_date' => '2026-09-22 11:00',
            'status' => 'pending', 'notified' => true, 'reminder_minutes_before' => 30]);

        $this->mobile('PUT', "/api/mobile/agenda/events/{$event->id}", ['title' => 'Cita renombrada'])->assertOk();
        $this->assertTrue($event->fresh()->notified, 'Cambiar solo el título no reprograma el aviso.');

        $this->mobile('PUT', "/api/mobile/agenda/events/{$event->id}", ['start_date' => '2026-09-23T10:00:00-06:00', 'end_date' => '2026-09-23T11:00:00-06:00'])->assertOk();
        $this->assertFalse($event->fresh()->notified);

        $event->update(['notified' => true]);
        $this->web('PUT', "/agenda/events/{$event->id}", ['title' => 'Cita', 'start_date' => '2026-09-24', 'start_time' => '09:30', 'end_date' => '2026-09-24', 'end_time' => '10:30'])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame(['2026-09-24 09:30', false], [$event->fresh()->start_date->format('Y-m-d H:i'), $event->fresh()->notified]);
    }

    public function test_web_create_update_and_delete_keep_their_contract(): void
    {
        $id = $this->web('POST', '/agenda/events', ['title' => 'Reunión', 'start_date' => '2026-09-22', 'start_time' => '15:00', 'end_time' => '16:00', 'reminder_minutes_before' => 15])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('event.title', 'Reunión')->json('event.id');
        $event = CalendarEvent::findOrFail($id);
        $this->assertSame(['2026-09-22 15:00', '2026-09-22 16:00', 15, $this->owner->id], [$event->start_date->format('Y-m-d H:i'), $event->end_date->format('Y-m-d H:i'),
            $event->reminder_minutes_before, $event->user_id]);

        $allDay = $this->web('POST', '/agenda/events', ['title' => 'Feriado', 'start_date' => '2026-09-25', 'all_day' => '1'])->assertOk()->json('event.id');
        $this->assertTrue(CalendarEvent::findOrFail($allDay)->all_day);

        $this->web('DELETE', "/agenda/events/{$id}")->assertOk()->assertJsonPath('success', true);
        $this->assertNull(CalendarEvent::find($id));
    }

    public function test_events_of_other_users_are_not_found(): void
    {
        $event = CalendarEvent::create(['user_id' => $this->owner->id, 'title' => 'Privado', 'start_date' => '2026-09-22 10:00', 'status' => 'pending']);
        $intruder = $this->user();

        $this->mobile('PUT', "/api/mobile/agenda/events/{$event->id}", ['title' => 'No'], [], $intruder)->assertNotFound();
        $this->mobile('DELETE', "/api/mobile/agenda/events/{$event->id}", [], [], $intruder)->assertNotFound();
        $this->assertSame('Privado', $event->fresh()->title);
    }

    public function test_natural_date_ranges(): void
    {
        $range = fn (string $text) => array_map(fn ($d) => $d->format('Y-m-d H:i'), AgendaDateRange::fromText($text, now())['range']);

        // Lunes 21 de septiembre de 2026, 08:00.
        $this->assertSame(['2026-09-28 00:00', '2026-10-04 23:59'], $range('¿Qué tengo la próxima semana?'));
        $this->assertSame(['2026-09-28 00:00', '2026-10-04 23:59'], $range('¿y la semana que viene?'));
        $this->assertSame(['2026-09-21 00:00', '2026-09-21 23:59'], $range('¿Qué tengo esta mañana?'));
        $this->assertSame(['2026-09-22 00:00', '2026-09-22 23:59'], $range('¿Qué tengo mañana?'));
        $this->assertSame(['2026-09-23 00:00', '2026-09-23 23:59'], $range('¿y pasado mañana?'));
        $this->assertSame(['2026-09-21 00:00', '2026-09-27 23:59'], $range('¿Qué tengo esta semana?'));
        $this->assertSame(['2026-09-25 00:00', '2026-09-25 23:59'], $range('¿Qué tengo el viernes?'));
        $this->assertSame(['2026-09-21 00:00', '2026-10-21 23:59'], $range('¿Qué tengo pendiente?'));
    }
}
