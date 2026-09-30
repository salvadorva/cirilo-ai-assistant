<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Guarda y lee fechas siempre en UTC, independientemente de APP_TIMEZONE.
 */
class UtcDateTime implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC');
    }

    public function set($model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        // Una cadena sin offset está en formato de BD, es decir, UTC; un offset explícito se respeta.
        $date = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value, 'UTC');

        return $date->utc()->format('Y-m-d H:i:s');
    }
}
