<?php

namespace Tests\Feature;

use App\Models\ContextualReminder;
use App\Models\ReminderCommand;
use App\Models\ReminderDelivery;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\SecurityTestCase;

/** RC5: retención aprobada (contenido 7 días tras el cierre; metadatos e idempotencia 30 días). */
class ReminderRetentionTest extends SecurityTestCase
{
    private User $owner;

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
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00', 'America/Guatemala'));
        Storage::fake('local');
        $this->owner = $this->user();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reminder(string $state, ?int $closedDaysAgo, array $extra = []): ContextualReminder
    {
        $closed = $closedDaysAgo === null ? null : now()->subDays($closedDaysAgo);

        return ContextualReminder::create($extra + ['user_id' => $this->owner->id, 'title' => 'Llamar al banco', 'context' => 'CONTEXTO_PRIVADO',
            'next_action' => 'ACCION_PRIVADA', 'scheduled_at' => now()->subDays(40), 'expires_at' => now()->subDays(39), 'timezone' => 'America/Guatemala',
            'state' => $state, 'version' => 2, 'confirmed_by_user' => true, 'confirmed_at' => now()->subDays(41),
            'completed_at' => $state === 'completed' ? $closed : null, 'cancelled_at' => $state === 'cancelled' ? $closed : null,
            'expired_at' => $state === 'expired' ? $closed : null]);
    }

    private function command(?ContextualReminder $reminder, int $daysAgo): ReminderCommand
    {
        $command = ReminderCommand::create(['actor_type' => 'integration', 'actor_id' => 1, 'idempotency_key' => 'k-'.uniqid(), 'operation' => 'create',
            'contextual_reminder_id' => $reminder?->id, 'request_hash' => str_repeat('a', 64), 'response_status' => 201,
            'response_body' => ['id' => $reminder?->id, 'title' => 'Llamar al banco', 'context' => 'CONTEXTO_PRIVADO', 'state' => 'pending']]);
        $command->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();

        return $command;
    }

    public function test_the_default_run_only_reports_and_changes_nothing(): void
    {
        $old = $this->reminder('completed', 10);
        $this->command($old, 10);

        $this->artisan('reminders:purge')->expectsOutputToContain('Modo informe')->assertExitCode(0);

        $this->assertSame('CONTEXTO_PRIVADO', $old->fresh()->context);
        $this->assertSame(1, ReminderCommand::count());
    }

    public function test_content_is_retired_seven_days_after_closing(): void
    {
        Storage::disk('local')->put('reminders/audio/viejo.mp3', 'mp3');
        $old = $this->reminder('completed', 8, ['with_audio' => true]);
        $old->forceFill(['audio_path' => 'reminders/audio/viejo.mp3'])->saveQuietly(); // no es asignable en masa
        $recent = $this->reminder('cancelled', 3);
        $pending = $this->reminder('pending', null);
        $command = $this->command($old, 8);

        $this->artisan('reminders:purge', ['--apply' => true])->assertExitCode(0);

        $old->refresh();
        $this->assertSame(['', '', null], [$old->context, $old->next_action, $old->audio_path]);
        $this->assertSame(ContextualReminder::CONTENT_RETIRED_TITLE, $old->title);
        $this->assertSame(['completed', 2], [$old->state, $old->version], 'Los metadatos operativos se conservan.');
        Storage::disk('local')->assertMissing('reminders/audio/viejo.mp3');
        $this->assertSame('CONTEXTO_PRIVADO', $recent->fresh()->context);
        $this->assertSame('CONTEXTO_PRIVADO', $pending->fresh()->context, 'Nunca se toca lo que sigue abierto.');
        // La respuesta idempotente guardada tampoco conserva el contenido.
        $body = $command->fresh()->response_body;
        $this->assertArrayNotHasKey('context', $body);
        $this->assertArrayNotHasKey('title', $body);
        $this->assertSame('pending', $body['state']);
    }

    public function test_metadata_and_idempotency_are_deleted_after_thirty_days(): void
    {
        $ancient = $this->reminder('expired', 31);
        ReminderDelivery::create(['source_type' => 'contextual', 'contextual_reminder_id' => $ancient->id, 'occurrence_key' => $ancient->id, 'revision' => 1,
            'destination_key' => 'd1', 'scheduled_at' => now()->subDays(40), 'expires_at' => now()->subDays(39), 'status' => 'accepted']);
        $routine = ReminderDelivery::create(['source_type' => 'routine', 'occurrence_key' => 'r-1', 'revision' => 1, 'destination_key' => 'd1',
            'scheduled_at' => now()->subDays(35), 'expires_at' => now()->subDays(35), 'status' => 'accepted']);
        $routine->forceFill(['created_at' => now()->subDays(35)])->saveQuietly();
        $kept = $this->reminder('completed', 20);
        $this->command(null, 31);
        $this->command($kept, 20);

        $this->artisan('reminders:purge', ['--apply' => true])->assertExitCode(0);

        $this->assertNull(ContextualReminder::find($ancient->id));
        $this->assertSame(0, ReminderDelivery::count(), 'Entregas del recordatorio borrado y de rutinas viejas.');
        $this->assertNotNull(ContextualReminder::find($kept->id));
        $this->assertSame(1, ReminderCommand::count());
    }
}
