<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserGameProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncAdminDashboardData extends Command
{
    protected $signature = 'admin:sync-data {--force : Forzar actualización de todos los datos}';

    protected $description = 'Sincroniza y arregla datos para el Admin Dashboard';

    public function handle()
    {
        $this->info('🔄 Sincronizando datos del Admin Dashboard...');
        $this->newLine();

        $force = $this->option('force');

        // 1. Crear GameProgress para usuarios sin datos
        $this->info('1️⃣ Verificando GameProgress...');
        $usersWithoutProgress = User::whereDoesntHave('gameProgress')->get();

        if ($usersWithoutProgress->count() > 0) {
            $this->line("   Encontrados {$usersWithoutProgress->count()} usuarios sin GameProgress");

            foreach ($usersWithoutProgress as $user) {
                UserGameProgress::create([
                    'user_id' => $user->id,
                    'level' => 1,
                    'total_xp' => 0,
                    'current_streak' => 0,
                    'longest_streak' => 0,
                    'last_activity_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->line("   ✅ GameProgress creado para: {$user->name}");
            }
        } else {
            $this->line('   ✅ Todos los usuarios tienen GameProgress');
        }
        $this->newLine();

        // 2. Sincronizar last_activity_at desde typing_sessions
        $this->info('2️⃣ Sincronizando last_activity_at desde typing_sessions...');

        $sessionsData = DB::table('typing_sessions')
            ->select('user_id', DB::raw('MAX(created_at) as last_session'))
            ->groupBy('user_id')
            ->get();

        if ($sessionsData->count() > 0) {
            foreach ($sessionsData as $session) {
                $progress = UserGameProgress::where('user_id', $session->user_id)->first();

                if ($progress && ($force || $progress->last_activity_at === null)) {
                    $progress->last_activity_at = $session->last_session;
                    $progress->save();

                    $user = User::find($session->user_id);
                    $userName = $user ? $user->name : "User #{$session->user_id}";
                    $this->line("   ✅ Actualizado last_activity_at para: {$userName} ({$session->last_session})");
                }
            }
        } else {
            $this->warn('   ⚠️  No hay typing_sessions para sincronizar');
        }
        $this->newLine();

        // 3. Recalcular XP desde typing_sessions si es necesario
        $this->info('3️⃣ Verificando consistencia de XP...');

        $progressRecords = UserGameProgress::with('user')->get();
        foreach ($progressRecords as $progress) {
            $sessionXP = DB::table('typing_sessions')
                ->where('user_id', $progress->user_id)
                ->sum('xp_earned');

            if ($sessionXP > 0 && ($force || $progress->total_xp != $sessionXP)) {
                $oldXP = $progress->total_xp;
                $progress->total_xp = $sessionXP;

                // Recalcular nivel
                $newLevel = $this->calculateLevel($sessionXP);
                $progress->level = $newLevel;
                $progress->save();

                $userName = $progress->user ? $progress->user->name : "User #{$progress->user_id}";
                $this->line("   ✅ XP actualizado para {$userName}: {$oldXP} → {$sessionXP} XP (Nivel {$newLevel})");
            }
        }
        $this->newLine();

        // 4. Resumen final
        $this->info('📊 RESUMEN FINAL:');
        $totalUsers = User::count();
        $usersWithProgress = UserGameProgress::count();
        $usersWithActivity = UserGameProgress::whereNotNull('last_activity_at')->count();

        $this->line("   Total usuarios: {$totalUsers}");
        $this->line("   Usuarios con GameProgress: {$usersWithProgress}");
        $this->line("   Usuarios con actividad: {$usersWithActivity}");

        $this->newLine();
        $this->info('✅ Sincronización completada');
        $this->info('💡 Ejecuta: php artisan admin:diagnose para verificar');

        return 0;
    }

    private function calculateLevel($xp)
    {
        // Fórmula: Nivel = floor(XP / 250) + 1
        // Cada 250 XP = 1 nivel
        return floor($xp / 250) + 1;
    }
}
