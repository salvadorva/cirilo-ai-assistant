<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserGameProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InactivityReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    public UserGameProgress $progress;

    public int $daysInactive;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, UserGameProgress $progress)
    {
        $this->user = $user;
        $this->progress = $progress;
        $this->daysInactive = $progress->days_inactive;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->daysInactive >= 14
            ? '¡Te extrañamos! Vuelve a TypeMaster AI 💚'
            : 'Tu racha te está esperando en TypeMaster AI ⚡';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.inactivity-reminder',
            with: [
                'userName' => $this->user->name,
                'daysInactive' => (int) round($this->daysInactive),
                'currentLevel' => $this->progress->level,
                'totalXP' => $this->progress->total_xp,
                'longestStreak' => $this->progress->longest_streak,
                'engagementScore' => $this->progress->engagement_score,
                'loginUrl' => url('/'),
                'settingsUrl' => url('/settings'),
            ]
        );
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
