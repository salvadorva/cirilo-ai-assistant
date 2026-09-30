<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/** RC5: producción no tiene un worker permanente; el scheduler vacía la cola cada minuto. */
class QueueWorkerScheduleTest extends TestCase
{
    public function test_the_queue_is_drained_every_minute_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'queue:work'));

        $this->assertNotNull($event, 'Falta el worker programado.');
        $this->assertStringContainsString('--stop-when-empty', $event->command);
        $this->assertStringContainsString('--max-time=50', $event->command);
        $this->assertStringContainsString('--tries=1', $event->command);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
