<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StreakService;
use Illuminate\Console\Command;

class RecalculateStreaks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'streaks:recalculate
                          {--user-id= : Recalcular racha para un usuario específico}
                          {--all : Recalcular rachas para todos los usuarios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcular rachas de usuarios basado en su historial de sesiones';

    /**
     * Execute the console command.
     */
    public function handle(StreakService $streakService)
    {
        $this->info('🔄 Iniciando recálculo de rachas...');
        $this->newLine();

        $userId = $this->option('user-id');
        $all = $this->option('all');

        if ($userId) {
            // Recalcular para un usuario específico
            $user = User::find($userId);

            if (! $user) {
                $this->error("❌ Usuario con ID {$userId} no encontrado");

                return 1;
            }

            $this->processUser($user, $streakService);
        } elseif ($all) {
            // Recalcular para todos los usuarios
            $users = User::whereHas('gameProgress')->get();

            if ($users->isEmpty()) {
                $this->warn('⚠️ No se encontraron usuarios con progreso de juego');

                return 0;
            }

            $this->info("📊 Procesando {$users->count()} usuarios...");
            $this->newLine();

            $bar = $this->output->createProgressBar($users->count());
            $bar->start();

            foreach ($users as $user) {
                $this->processUser($user, $streakService, false);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->newLine();
            $this->info('✅ Recálculo completado para todos los usuarios');
        } else {
            $this->error('❌ Debes especificar --user-id=X o --all');

            return 1;
        }

        return 0;
    }

    /**
     * Procesar un usuario individual
     */
    private function processUser(User $user, StreakService $streakService, bool $verbose = true): void
    {
        $result = $streakService->recalculateStreakFromHistory($user->id);

        if ($verbose) {
            $this->info("👤 Usuario: {$user->name} (ID: {$user->id})");
            $this->line("   📅 Días activos totales: {$result['total_active_days']}");
            $this->line("   🔥 Racha actual: {$result['current_streak']} días");
            $this->line("   🏆 Racha más larga: {$result['longest_streak']} días");
            $this->line("   📆 Última actividad: {$result['last_activity']}");

            if ($result['current_streak'] > 0) {
                $this->line('   ✅ Racha activa');
            } else {
                $this->warn('   ⚠️ Sin racha activa');
            }

            $this->newLine();
        }
    }
}
