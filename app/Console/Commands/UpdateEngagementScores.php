<?php

namespace App\Console\Commands;

use App\Services\InactivityDetector;
use Illuminate\Console\Command;

class UpdateEngagementScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'engagement:update-scores 
                            {--reset-weekly : Reset weekly session counters}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update engagement scores and risk levels for all users';

    private InactivityDetector $detector;

    /**
     * Create a new command instance.
     */
    public function __construct(InactivityDetector $detector)
    {
        parent::__construct();
        $this->detector = $detector;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Actualizando scores de engagement...');

        $updated = $this->detector->updateAllEngagementScores();

        $this->info("✅ Actualizados {$updated} scores de engagement");

        if ($this->option('reset-weekly')) {
            $this->info('🔄 Reseteando contadores semanales...');
            $resetCount = $this->detector->resetWeeklyCounters();
            $this->info("✅ Reseteados contadores de {$resetCount} usuarios");
        }

        // Mostrar estadísticas
        $stats = $this->detector->getEngagementStats();

        $this->info("\n📊 Estadísticas de Engagement:");
        $this->info("Total de usuarios: {$stats['total_users']}");

        $this->table(
            ['Nivel de Riesgo', 'Usuarios', 'Engagement Promedio'],
            [
                ['Bajo', $stats['risk_distribution']['low']->count ?? 0, round($stats['risk_distribution']['low']->avg_engagement ?? 0, 1)],
                ['Medio', $stats['risk_distribution']['medium']->count ?? 0, round($stats['risk_distribution']['medium']->avg_engagement ?? 0, 1)],
                ['Alto', $stats['risk_distribution']['high']->count ?? 0, round($stats['risk_distribution']['high']->avg_engagement ?? 0, 1)],
                ['Crítico', $stats['risk_distribution']['critical']->count ?? 0, round($stats['risk_distribution']['critical']->avg_engagement ?? 0, 1)],
            ]
        );

        return Command::SUCCESS;
    }
}
