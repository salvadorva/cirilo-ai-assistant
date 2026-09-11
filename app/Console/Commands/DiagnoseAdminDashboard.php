<?php

namespace App\Console\Commands;

use App\Models\CourseProgress;
use App\Models\User;
use App\Models\UserGameProgress;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseAdminDashboard extends Command
{
    protected $signature = 'admin:diagnose';

    protected $description = 'Diagnostica problemas con datos del Admin Dashboard';

    public function handle()
    {
        $this->info('🔍 Diagnosticando Admin Dashboard...');
        $this->newLine();

        // 1. Usuarios básicos
        $this->info('📊 1. USUARIOS BÁSICOS');
        $totalUsers = User::count();
        $usersWithProgress = UserGameProgress::count();
        $usersWithActivity = UserGameProgress::whereNotNull('last_activity_at')->count();

        $this->line("   Total usuarios: {$totalUsers}");
        $this->line("   Usuarios con GameProgress: {$usersWithProgress}");
        $this->line("   Usuarios con actividad registrada: {$usersWithActivity}");

        if ($usersWithProgress < $totalUsers) {
            $this->warn('   ⚠️  Faltan '.($totalUsers - $usersWithProgress).' usuarios sin GameProgress inicializado');
        }

        if ($usersWithActivity === 0) {
            $this->error('   ❌ NINGÚN usuario tiene last_activity_at - Gráficos estarán vacíos');
        }

        $this->newLine();

        // 2. Distribución de niveles
        $this->info('📊 2. DISTRIBUCIÓN DE NIVELES');
        $levelDistribution = UserGameProgress::select('level', DB::raw('count(*) as count'))
            ->groupBy('level')
            ->orderBy('level')
            ->get();

        if ($levelDistribution->isEmpty()) {
            $this->error('   ❌ No hay datos de distribución de niveles');
        } else {
            foreach ($levelDistribution as $level) {
                // @phpstan-ignore-next-line
                $this->line("   Nivel {$level->level}: {$level->count} usuarios");
            }
        }
        $this->newLine();

        // 3. Top usuarios
        $this->info('📊 3. TOP USUARIOS POR XP');
        $topUsers = UserGameProgress::with('user')
            ->orderBy('total_xp', 'desc')
            ->take(5)
            ->get();

        if ($topUsers->isEmpty()) {
            $this->error('   ❌ No hay datos de XP');
        } else {
            foreach ($topUsers as $progress) {
                $userName = $progress->user ? $progress->user->name : 'Unknown';
                $this->line("   {$userName}: {$progress->total_xp} XP (Nivel {$progress->level})");
            }
        }
        $this->newLine();

        // 4. Top rachas
        $this->info('📊 4. TOP USUARIOS POR RACHA');
        $topStreaks = UserGameProgress::with('user')
            ->orderBy('current_streak', 'desc')
            ->take(5)
            ->get();

        if ($topStreaks->isEmpty() || $topStreaks->every(fn ($u) => $u->current_streak == 0)) {
            $this->error('   ❌ No hay rachas activas');
        } else {
            foreach ($topStreaks as $progress) {
                $userName = $progress->user ? $progress->user->name : 'Unknown';
                $this->line("   {$userName}: {$progress->current_streak} días (Mejor: {$progress->longest_streak})");
            }
        }
        $this->newLine();

        // 5. Actividad reciente
        $this->info('📊 5. ACTIVIDAD RECIENTE (Últimos 30 días)');
        $recentActivity = UserGameProgress::where('last_activity_at', '>=', Carbon::now()->subDays(30))
            ->select(DB::raw('DATE(last_activity_at) as date'), DB::raw('COUNT(DISTINCT user_id) as users'))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->take(10)
            ->get();

        if ($recentActivity->isEmpty()) {
            $this->error('   ❌ No hay actividad en los últimos 30 días');
        } else {
            foreach ($recentActivity as $activity) {
                // @phpstan-ignore-next-line
                $this->line("   {$activity->date}: {$activity->users} usuarios");
            }
        }
        $this->newLine();

        // 6. Typing sessions
        $this->info('📊 6. TYPING SESSIONS');
        $totalSessions = DB::table('typing_sessions')->count();
        $recentSessions = DB::table('typing_sessions')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $this->line("   Total sesiones: {$totalSessions}");
        $this->line("   Sesiones últimos 30 días: {$recentSessions}");

        if ($totalSessions > 0 && $usersWithActivity === 0) {
            $this->warn('   ⚠️  HAY SESIONES pero last_activity_at está NULL - Problema de sincronización');
        }
        $this->newLine();

        // 7. Logros
        $this->info('📊 7. LOGROS');
        $totalAchievements = DB::table('achievements')->count();
        $unlockedAchievements = DB::table('user_achievements')->count();

        $this->line("   Total logros disponibles: {$totalAchievements}");
        $this->line("   Logros desbloqueados: {$unlockedAchievements}");

        if ($totalAchievements === 0) {
            $this->warn('   ⚠️  No hay logros definidos en el sistema');
        }
        $this->newLine();

        // 8. Course Progress
        $this->info('📊 8. PROGRESO DE CURSOS');
        $totalCourseProgress = CourseProgress::count();
        $completedCourses = CourseProgress::where('completed', true)->count();

        $this->line("   Registros de progreso: {$totalCourseProgress}");
        $this->line("   Cursos completados: {$completedCourses}");
        $this->newLine();

        // 9. Verificar estructura de datos
        $this->info('📊 9. MUESTRA DE DATOS (Primeros 3 usuarios con GameProgress)');
        $sampleData = UserGameProgress::with('user')
            ->take(3)
            ->get();

        foreach ($sampleData as $progress) {
            $userName = $progress->user ? $progress->user->name : 'Unknown';
            $lastActivity = $progress->last_activity_at ? $progress->last_activity_at->format('Y-m-d H:i') : 'NULL';

            $this->line("   👤 {$userName}:");
            $this->line("      - XP: {$progress->total_xp}");
            $this->line("      - Nivel: {$progress->level}");
            $this->line("      - Racha: {$progress->current_streak} días");
            $this->line("      - Última actividad: {$lastActivity}");
        }
        $this->newLine();

        // Resumen y recomendaciones
        $this->info('🎯 RESUMEN Y RECOMENDACIONES');

        $issues = [];
        if ($usersWithProgress < $totalUsers) {
            $issues[] = 'Inicializar GameProgress para usuarios faltantes';
        }
        if ($usersWithActivity === 0) {
            $issues[] = 'Actualizar last_activity_at desde typing_sessions';
        }
        if ($totalAchievements === 0) {
            $issues[] = 'Ejecutar seeder de logros (AchievementsSeeder)';
        }
        if ($levelDistribution->isEmpty()) {
            $issues[] = 'No hay datos para mostrar en gráficos';
        }

        if (empty($issues)) {
            $this->info('   ✅ Todo parece estar bien con los datos');
        } else {
            $this->warn('   Problemas detectados:');
            foreach ($issues as $issue) {
                $this->line("   ❌ {$issue}");
            }
        }

        $this->newLine();
        $this->info('✅ Diagnóstico completado');

        return 0;
    }
}
