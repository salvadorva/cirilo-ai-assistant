<?php

namespace App\Services\Agenda;

use App\Models\CalendarEvent;

/**
 * Resultado de una operación de agenda. Solo `created`, `replayed` y `duplicate` implican que el
 * evento existe en la base; el resto nunca debe confirmarse como éxito.
 */
final class AgendaResult
{
    public const CREATED = 'created';

    public const REPLAYED = 'replayed';

    public const DUPLICATE = 'duplicate';

    public const CONFLICT = 'conflict';

    public const INVALID = 'invalid';

    public const FAILED = 'failed';

    /** @param CalendarEvent[] $events */
    public function __construct(
        public readonly string $status,
        public readonly array $events = [],
        public readonly ?string $message = null,
        public readonly ?string $recurrenceSummary = null,
    ) {}

    public function persisted(): bool
    {
        return in_array($this->status, [self::CREATED, self::REPLAYED, self::DUPLICATE], true) && $this->events !== [];
    }

    public function first(): ?CalendarEvent
    {
        return $this->events[0] ?? null;
    }
}
