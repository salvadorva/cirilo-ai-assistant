<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LinkRecurringSeries extends Command
{
    protected $signature = 'agenda:link-series
                            {--apply : Aplica los cambios (sin este flag es modo dry-run)}';

    protected $description = 'Agrupa eventos existentes con el mismo título y usuario bajo un series_id compartido';

    public function handle(): int
    {
        $apply   = $this->option('apply');
        $mode    = $apply ? '<fg=yellow>APLICANDO</>     ' : '<fg=cyan>DRY-RUN (solo vista)</>';

        $this->line('');
        $this->line("  Modo: {$mode}");
        $this->line('  Criterio: eventos con mismo título y usuario, sin series_id, con ≥ 2 ocurrencias');
        $this->line('');

        // Obtener grupos: (user_id, title) con más de 1 evento sin series_id
        $groups = CalendarEvent::whereNull('series_id')
            ->select('user_id', 'title')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('user_id', 'title')
            ->having('total', '>=', 2)
            ->orderBy('user_id')
            ->orderBy('title')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('  No se encontraron grupos de eventos para vincular.');
            return self::SUCCESS;
        }

        $totalGroups = $groups->count();
        $totalEvents = 0;

        $headers = ['Usuario ID', 'Título', 'Eventos', 'Series ID (nuevo)'];
        $rows    = [];

        foreach ($groups as $group) {
            $seriesId = Str::uuid()->toString();

            $events = CalendarEvent::whereNull('series_id')
                ->where('user_id', $group->user_id)
                ->where('title', $group->title)
                ->orderBy('start_date')
                ->get();

            $rows[] = [
                $group->user_id,
                mb_strimwidth($group->title, 0, 45, '…'),
                $events->count(),
                $apply ? $seriesId : '(pendiente)',
            ];

            $totalEvents += $events->count();

            if ($apply) {
                foreach ($events as $event) {
                    $event->series_id = $seriesId;
                    $event->save();
                }
            }
        }

        $this->table($headers, $rows);
        $this->line('');

        if ($apply) {
            $this->info("  ✓ Se asignaron series_id a {$totalEvents} eventos en {$totalGroups} grupos.");
        } else {
            $this->comment("  Se vincularían {$totalEvents} eventos en {$totalGroups} grupos.");
            $this->line('  Para aplicar los cambios ejecuta:');
            $this->line('  <fg=green>php artisan agenda:link-series --apply</>');
        }

        $this->line('');
        return self::SUCCESS;
    }
}
