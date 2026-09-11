<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserActivityTracker;
use App\Models\UserGameProgress;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public UserGameProgress $progress;

    public array $weeklyStats;

    public array $achievements;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, UserGameProgress $progress)
    {
        $this->user = $user;
        $this->progress = $progress;
        $this->weeklyStats = $this->calculateWeeklyStats();
        $this->achievements = $this->detectAchievements();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $weekNumber = Carbon::now()->weekOfYear;

        return new Envelope(
            subject: "📊 Tu reporte semanal #{$weekNumber} - TypeMaster AI",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.weekly-report',
            with: [
                'userName' => $this->user->name,
                'weekNumber' => Carbon::now()->weekOfYear,
                'currentLevel' => $this->progress->level,
                'totalXP' => $this->progress->total_xp,
                'currentStreak' => $this->progress->current_streak,
                'longestStreak' => $this->progress->longest_streak,
                'engagementScore' => $this->progress->engagement_score,
                'weeklyStats' => $this->weeklyStats,
                'achievements' => $this->achievements,
                'nextGoals' => $this->generateNextGoals(),
                'loginUrl' => url('/'),
                'settingsUrl' => url('/settings'),
            ]
        );
    }

    /**
     * Calcular estadísticas de la semana
     */
    private function calculateWeeklyStats(): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $activities = UserActivityTracker::where('user_id', $this->user->id)
            ->whereBetween('session_start', [$startOfWeek, $endOfWeek])
            ->get();

        $totalSessions = $activities->count();
        $totalTime = $activities->sum('duration_seconds');
        $totalXP = $activities->sum('xp_earned');
        $avgSessionTime = $totalSessions > 0 ? round($totalTime / $totalSessions / 60, 1) : 0;

        // Distribución por tipo de actividad
        $activityTypes = $activities->groupBy('activity_type')->map->count();

        // Calidad de sesiones
        $qualityDistribution = $activities->groupBy('session_quality')->map->count();

        return [
            'total_sessions' => $totalSessions,
            'total_time_minutes' => round($totalTime / 60, 1),
            'total_xp_earned' => $totalXP,
            'average_session_minutes' => $avgSessionTime,
            'activity_types' => $activityTypes->toArray(),
            'quality_distribution' => $qualityDistribution->toArray(),
            'days_active' => $activities->pluck('session_start')->map(function ($date) {
                return Carbon::parse($date)->format('Y-m-d');
            })->unique()->count(),
        ];
    }

    /**
     * Detectar logros de la semana
     */
    private function detectAchievements(): array
    {
        $achievements = [];

        // Logro por sesiones
        if ($this->weeklyStats['total_sessions'] >= 7) {
            $achievements[] = [
                'icon' => '🔥',
                'title' => 'Semana Perfecta',
                'description' => 'Jugaste todos los días de la semana',
            ];
        } elseif ($this->weeklyStats['total_sessions'] >= 5) {
            $achievements[] = [
                'icon' => '⭐',
                'title' => 'Muy Consistente',
                'description' => 'Jugaste 5+ días esta semana',
            ];
        }

        // Logro por tiempo
        if ($this->weeklyStats['total_time_minutes'] >= 120) {
            $achievements[] = [
                'icon' => '⏰',
                'title' => 'Maratonista',
                'description' => 'Más de 2 horas de práctica',
            ];
        }

        // Logro por XP
        if ($this->weeklyStats['total_xp_earned'] >= 500) {
            $achievements[] = [
                'icon' => '💎',
                'title' => 'Coleccionista de XP',
                'description' => 'Ganaste más de 500 XP',
            ];
        }

        // Logro por racha
        if ($this->progress->current_streak >= 7) {
            $achievements[] = [
                'icon' => '🏆',
                'title' => 'Leyenda de Rachas',
                'description' => "Racha de {$this->progress->current_streak} días",
            ];
        }

        return $achievements;
    }

    /**
     * Generar objetivos para la próxima semana
     */
    private function generateNextGoals(): array
    {
        $goals = [];

        // Goal based on current performance
        $currentSessions = $this->weeklyStats['total_sessions'];
        $targetSessions = min(7, $currentSessions + 1);

        $goals[] = [
            'type' => 'sessions',
            'title' => "Jugar {$targetSessions} días",
            'description' => 'Mantén la consistencia',
            'current' => $currentSessions,
            'target' => $targetSessions,
        ];

        // XP goal
        $currentXP = $this->weeklyStats['total_xp_earned'];
        $targetXP = max(100, $currentXP + 50);

        $goals[] = [
            'type' => 'xp',
            'title' => "Ganar {$targetXP} XP",
            'description' => 'Mejora tu puntuación',
            'current' => $currentXP,
            'target' => $targetXP,
        ];

        return $goals;
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
