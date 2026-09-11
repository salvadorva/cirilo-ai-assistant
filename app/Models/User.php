<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property int|null $role_id
 * @property string|null $prompt
 * @property string|null $ai_provider
 * @property int|null $age
 * @property bool $email_notifications_enabled
 * @property bool $agenda_reminders_enabled
 * @property string|null $telegram_chat_id
 * @property bool $telegram_notifications_enabled
 * @property string|null $bio
 * @property string|null $avatar
 * @property int $daily_image_limit
 * @property \Carbon\Carbon|null $last_login_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Role|null $role
 * @property-read \App\Models\UserGameProgress|null $gameProgress
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Achievement> $achievements
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CourseProgress> $courseProgress
 */
class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'age',
        'email_notifications_enabled',
        'agenda_reminders_enabled',
        'telegram_chat_id',
        'telegram_notifications_enabled',
        'bio',
        'avatar',
        'prompt',
        'ai_provider',
        'daily_image_limit',
        'nextcloud_url',
        'nextcloud_username',
        'nextcloud_password',
        'nextcloud_calendar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'              => 'datetime',
            'password'                       => 'hashed',
            'nextcloud_password'             => 'encrypted',
            'telegram_notifications_enabled' => 'boolean',
            'email_notifications_enabled'    => 'boolean',
            'agenda_reminders_enabled'       => 'boolean',
        ];
    }

    public function hasNextcloud(): bool
    {
        return ! empty($this->nextcloud_url)
            && ! empty($this->nextcloud_username)
            && ! empty($this->nextcloud_password);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return HasMany<UserEnglishLevel, $this> */
    public function englishLevels(): HasMany
    {
        return $this->hasMany(UserEnglishLevel::class);
    }

    /** @return HasMany<ExerciseResult, $this> */
    public function exerciseResults(): HasMany
    {
        return $this->hasMany(ExerciseResult::class);
    }

    public function gameProgress(): HasOne
    {
        return $this->hasOne(UserGameProgress::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function courseProgress(): HasMany
    {
        return $this->hasMany(CourseProgress::class);
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at', 'unlock_data', 'is_showcased')
            ->withTimestamps();
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function dailyStreaks(): HasMany
    {
        return $this->hasMany(DailyStreak::class);
    }

    public function typingSessions(): HasMany
    {
        return $this->hasMany(TypingSession::class);
    }

    /**
     * Obtener o crear progreso de juego del usuario
     */
    public function getOrCreateGameProgress()
    {
        return $this->gameProgress ?: $this->gameProgress()->create([
            'total_xp' => 0,
            'level' => 1,
            'xp_to_next_level' => 100,
        ]);
    }

    /**
     * Obtener notificaciones no leídas
     */
    public function getUnreadNotificationsCount()
    {
        return $this->notifications()->unread()->active()->count();
    }

    /**
     * Obtener logros destacados por el usuario
     */
    public function getShowcasedAchievements()
    {
        return $this->userAchievements()
            ->showcased()
            ->with('achievement')
            ->limit(3)
            ->get();
    }

    /**
     * Obtener categoría de edad del usuario
     */
    public function getAgeCategory()
    {
        if (! $this->age) {
            return 'adult'; // Por defecto si no hay edad
        }

        if ($this->age >= 6 && $this->age <= 12) {
            return 'child';
        } elseif ($this->age >= 13 && $this->age <= 17) {
            return 'teen';
        } elseif ($this->age >= 65) {
            return 'senior';
        } else {
            return 'adult';
        }
    }

    /**
     * Obtener multiplicador de velocidad según edad
     */
    public function getSpeedMultiplier()
    {
        switch ($this->getAgeCategory()) {
            case 'child':
                return 0.5; // 50% de la velocidad objetivo
            case 'teen':
                return 0.75; // 75% de la velocidad objetivo
            case 'senior':
                return 0.8; // 80% de la velocidad objetivo
            default:
                return 1.0; // 100% de la velocidad objetivo
        }
    }

    /**
     * Obtener WPM objetivo ajustado por edad
     */
    public function getAdjustedTargetWpm($baseWpm)
    {
        return (int) round($baseWpm * $this->getSpeedMultiplier());
    }
}
