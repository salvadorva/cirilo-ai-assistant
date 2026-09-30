<?php

namespace App\Services\Agenda;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * F2-03: rango de consulta a partir de expresiones como «esta mañana», «la próxima semana» o
 * «el viernes». El orden importa: «pasado mañana» y «esta mañana» antes que «mañana».
 */
class AgendaDateRange
{
    private const WEEKDAYS = ['domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6];

    private const DAY_NAMES = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    /** @return array{range: array{0: Carbon, 1: Carbon}, period: string} */
    public static function fromText(string $text, CarbonInterface $now): array
    {
        $now = Carbon::instance($now)->setTimezone(config('app.timezone'));
        $text = strtr(mb_strtolower($text), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        $day = fn (Carbon $d, string $period) => ['range' => [$d->copy()->startOfDay(), $d->copy()->endOfDay()], 'period' => $period];

        if (preg_match('/\bpasado manana\b/', $text)) {
            return $day($now->copy()->addDays(2), 'pasado mañana');
        }
        if (preg_match('/\b(esta manana|esta tarde|esta noche|hoy)\b/', $text)) {
            return $day($now, 'hoy');
        }
        if (preg_match('/\b(proxima semana|semana que viene|semana proxima|siguiente semana)\b/', $text)) {
            $start = $now->copy()->addWeek()->startOfWeek(Carbon::MONDAY);

            return ['range' => [$start, $start->copy()->endOfWeek(Carbon::SUNDAY)], 'period' => 'la próxima semana'];
        }
        if (preg_match('/\b(esta semana|la semana)\b/', $text)) {
            return ['range' => [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY)], 'period' => 'esta semana'];
        }
        if (preg_match('/\bmanana\b/', $text)) {
            return $day($now->copy()->addDay(), 'mañana');
        }
        foreach (self::WEEKDAYS as $name => $dayOfWeek) {
            if (preg_match('/\b'.$name.'\b/', $text)) {
                $date = $now->copy();
                while ($date->dayOfWeek !== $dayOfWeek) {
                    $date->addDay();
                }

                return $day($date, 'el '.self::DAY_NAMES[$dayOfWeek]);
            }
        }

        return ['range' => [$now->copy()->startOfDay(), $now->copy()->addDays(30)->endOfDay()], 'period' => 'los próximos 30 días'];
    }
}
