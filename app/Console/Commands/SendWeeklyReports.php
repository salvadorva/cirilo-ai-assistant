<?php

namespace App\Console\Commands;

use App\Mail\WeeklyReportMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWeeklyReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'engagement:send-weekly-reports 
                            {--user-id= : Send report to specific user ID}
                            {--dry-run : Show what would be sent without sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send weekly engagement reports to active users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('📊 Enviando reportes semanales...');

        // Obtener usuarios activos (que han tenido actividad en los últimos 30 días)
        $users = $this->getTargetUsers();

        if ($users->isEmpty()) {
            $this->info('ℹ️ No hay usuarios activos para enviar reportes');

            return Command::SUCCESS;
        }

        $this->info("📧 Enviando reportes a {$users->count()} usuarios");

        if ($this->option('dry-run')) {
            $this->showReportPlan($users);

            return Command::SUCCESS;
        }

        $this->sendReports($users);

        return Command::SUCCESS;
    }

    /**
     * Obtener usuarios objetivo para los reportes
     */
    private function getTargetUsers()
    {
        if ($userId = $this->option('user-id')) {
            return User::with('gameProgress')->where('id', $userId)->get();
        }

        return User::with('gameProgress')
            ->whereHas('gameProgress', function ($query) {
                $query->where('days_inactive', '<=', 30)
                    ->where('total_sessions', '>', 0);
            })
            ->get();
    }

    /**
     * Enviar reportes a los usuarios
     */
    private function sendReports($users)
    {
        $reportsSent = 0;
        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            try {
                if (! $user->gameProgress) {
                    $this->warn("\nUsuario {$user->id} sin progreso, saltando...");

                    continue;
                }

                // Verificar si el usuario tiene habilitadas las notificaciones por email
                if (! $user->email_notifications_enabled) {
                    Log::info("Usuario {$user->id} ({$user->email}) tiene deshabilitadas las notificaciones por email");
                    $progressBar->advance();

                    continue;
                }

                Mail::to($user->email)->send(
                    new WeeklyReportMail($user, $user->gameProgress)
                );

                $reportsSent++;
                Log::info("Reporte semanal enviado a usuario {$user->id} ({$user->email})");

            } catch (\Exception $e) {
                Log::error("Error enviando reporte a {$user->email}: ".$e->getMessage());
                $this->error("\nError enviando reporte a {$user->email}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n✅ Enviados {$reportsSent} reportes semanales");
    }

    /**
     * Mostrar plan de reportes para dry-run
     */
    private function showReportPlan($users)
    {
        $this->info('📋 Reportes que se enviarían:');

        $reportPlan = $users->map(function ($user) {
            $progress = $user->gameProgress;

            return [
                'ID' => $user->id,
                'Email' => $user->email,
                'Nivel' => $progress ? $progress->level : 'N/A',
                'XP Total' => $progress ? number_format($progress->total_xp) : 'N/A',
                'Días Inactivo' => $progress ? $progress->days_inactive : 'N/A',
                'Engagement' => $progress ? round($progress->engagement_score, 1) : 'N/A',
            ];
        })->toArray();

        $this->table(
            ['ID', 'Email', 'Nivel', 'XP Total', 'Días Inactivo', 'Engagement'],
            $reportPlan
        );
    }
}
