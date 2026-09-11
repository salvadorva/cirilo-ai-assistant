<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Console\Command;

class GrantRetroactiveAchievements extends Command
{
    protected $signature = 'achievements:grant-retroactive {--user-id= : ID del usuario específico (opcional)}';

    protected $description = 'Otorga logros retroactivamente basado en estadísticas existentes';

    private $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        parent::__construct();
        $this->achievementService = $achievementService;
    }

    public function handle()
    {
        $this->info('🏆 Otorgando logros retroactivos...');
        $this->newLine();

        $userId = $this->option('user-id');

        if ($userId) {
            $users = User::where('id', $userId)->get();
            if ($users->isEmpty()) {
                $this->error("Usuario con ID {$userId} no encontrado");

                return 1;
            }
        } else {
            $users = User::all();
        }

        $totalGranted = 0;

        foreach ($users as $user) {
            $this->info("Verificando usuario: {$user->name} (ID: {$user->id})");

            $granted = $this->achievementService->grantRetroactiveAchievements($user);

            if (! empty($granted)) {
                foreach ($granted as $achievement) {
                    $this->line("  ✅ {$achievement->name} (+{$achievement->xp_reward} XP)");
                    $totalGranted++;
                }
            } else {
                $this->line('  ℹ️  No hay nuevos logros para este usuario');
            }

            $this->newLine();
        }

        $this->info('✅ Proceso completado');
        $this->info("Total de logros otorgados: {$totalGranted}");

        return 0;
    }
}
