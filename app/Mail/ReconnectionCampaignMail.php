<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserGameProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReconnectionCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public UserGameProgress $progress;

    public string $campaignType;

    public array $incentives;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, UserGameProgress $progress, string $campaignType = 'standard')
    {
        $this->user = $user;
        $this->progress = $progress;
        $this->campaignType = $campaignType;
        $this->incentives = $this->generateIncentives();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjects = [
            'standard' => '¡Regresa a TypeMaster! Te hemos preparado algo especial 🎁',
            'urgent' => '⚠️ Tu cuenta está en riesgo - ¡Actívala ahora!',
            'final' => 'Última oportunidad - ¿Nos das otra chance? 💔',
        ];

        return new Envelope(
            subject: $subjects[$this->campaignType] ?? $subjects['standard'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.reconnection-campaign',
            with: [
                'userName' => $this->user->name,
                'campaignType' => $this->campaignType,
                'daysInactive' => (int) round($this->progress->days_inactive),
                'currentLevel' => $this->progress->level,
                'totalXP' => $this->progress->total_xp,
                'longestStreak' => $this->progress->longest_streak,
                'incentives' => $this->incentives,
                'loginUrl' => url('/'),
                'settingsUrl' => url('/settings'),
            ]
        );
    }

    /**
     * Generar incentivos basados en el progreso del usuario
     */
    private function generateIncentives(): array
    {
        $incentives = [];

        // Bonus XP
        $bonusXP = min(100, $this->progress->days_inactive * 5);
        $incentives[] = [
            'type' => 'xp_bonus',
            'title' => "Bonus de {$bonusXP} XP",
            'description' => '¡XP gratis por regresar!',
        ];

        // Racha protection
        if ($this->progress->longest_streak > 3) {
            $incentives[] = [
                'type' => 'streak_protection',
                'title' => 'Protección de racha',
                'description' => "Recupera tu racha de {$this->progress->longest_streak} días",
            ];
        }

        // Level boost
        if ($this->campaignType === 'urgent' && $this->progress->level > 1) {
            $incentives[] = [
                'type' => 'level_boost',
                'title' => 'Impulso de nivel',
                'description' => 'Progreso 50% más rápido por 3 días',
            ];
        }

        return $incentives;
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
