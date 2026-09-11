<?php

namespace App\Console\Commands;

use App\Mail\InactivityReminderMail;
use App\Mail\ReconnectionCampaignMail;
use App\Services\InactivityDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DetectInactiveUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'engagement:detect-inactive 
                            {--send-emails : Send emails to inactive users}
                            {--dry-run : Show what would be done without sending emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect inactive users and optionally send re-engagement emails';

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
        $this->info('🔍 Detectando usuarios inactivos...');

        $inactiveUsers = $this->detector->detectInactiveUsers();

        if ($inactiveUsers->isEmpty()) {
            $this->info('✅ No se encontraron usuarios inactivos');

            return Command::SUCCESS;
        }

        $this->info("📊 Encontrados {$inactiveUsers->count()} usuarios inactivos:");

        // Mostrar tabla con usuarios inactivos
        $tableData = $inactiveUsers->map(function ($item) {
            return [
                'ID' => $item['user']->id,
                'Nombre' => $item['user']->name,
                'Email' => $item['user']->email,
                'Días Inactivo' => $item['days_inactive'],
                'Nivel de Riesgo' => $item['risk_level'],
                'Nivel' => $item['progress']->level,
                'XP Total' => number_format($item['progress']->total_xp),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Nombre', 'Email', 'Días Inactivo', 'Riesgo', 'Nivel', 'XP'],
            $tableData
        );

        // Agrupar por nivel de riesgo
        $byRisk = $inactiveUsers->groupBy('risk_level');

        foreach ($byRisk as $riskLevel => $users) {
            $this->line('');
            $this->warn("📋 {$riskLevel}: {$users->count()} usuarios");
        }

        // Enviar emails si se solicita
        if ($this->option('send-emails') && ! $this->option('dry-run')) {
            $this->sendEngagementEmails($inactiveUsers);
        } elseif ($this->option('dry-run')) {
            $this->info("\n🧪 Modo DRY-RUN: No se enviarán emails");
            $this->showEmailPlan($inactiveUsers);
        } else {
            $this->info("\n💡 Tip: Usa --send-emails para enviar emails o --dry-run para ver el plan");
        }

        return Command::SUCCESS;
    }

    /**
     * Enviar emails de engagement
     */
    private function sendEngagementEmails($inactiveUsers)
    {
        $this->info("\n📧 Enviando emails de re-engagement...");

        $emailsSent = 0;
        $progressBar = $this->output->createProgressBar($inactiveUsers->count());
        $progressBar->start();

        foreach ($inactiveUsers as $item) {
            $user = $item['user'];
            $progress = $item['progress'];
            $daysInactive = $item['days_inactive'];

            try {
                // Verificar si el usuario tiene habilitadas las notificaciones por email
                if (! $user->email_notifications_enabled) {
                    Log::info("Usuario {$user->id} ({$user->email}) tiene deshabilitadas las notificaciones por email");
                    $progressBar->advance();

                    continue;
                }

                if ($daysInactive >= 21) {
                    // Campaña urgente para usuarios muy inactivos
                    Mail::to($user->email)->send(
                        new ReconnectionCampaignMail($user, $progress, 'urgent')
                    );
                } elseif ($daysInactive >= 14) {
                    // Campaña estándar
                    Mail::to($user->email)->send(
                        new ReconnectionCampaignMail($user, $progress, 'standard')
                    );
                } else {
                    // Recordatorio suave
                    Mail::to($user->email)->send(
                        new InactivityReminderMail($user, $progress)
                    );
                }

                $emailsSent++;
                Log::info("Email enviado a usuario {$user->id} ({$user->email})");

            } catch (\Exception $e) {
                Log::error("Error enviando email a {$user->email}: ".$e->getMessage());
                $this->error("\nError enviando email a {$user->email}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n✅ Enviados {$emailsSent} emails de re-engagement");
    }

    /**
     * Mostrar plan de emails para dry-run
     */
    private function showEmailPlan($inactiveUsers)
    {
        $this->info('📋 Plan de emails que se enviarían:');

        $emailPlan = $inactiveUsers->map(function ($item) {
            $daysInactive = $item['days_inactive'];

            if ($daysInactive >= 21) {
                $emailType = 'Campaña Urgente';
            } elseif ($daysInactive >= 14) {
                $emailType = 'Campaña Estándar';
            } else {
                $emailType = 'Recordatorio Suave';
            }

            return [
                'Email' => $item['user']->email,
                'Días Inactivo' => $daysInactive,
                'Tipo de Email' => $emailType,
            ];
        })->toArray();

        $this->table(['Email', 'Días Inactivo', 'Tipo de Email'], $emailPlan);
    }
}
