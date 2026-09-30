<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $user_id
 * @property string $token
 * @property string $platform
 * @property \Carbon\Carbon|null $last_used_at
 * @property array|null $capabilities
 * @property \Carbon\Carbon|null $disabled_at
 */
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'last_used_at',
        'installation_id',
        'capabilities',
        'app_version',
        'disabled_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'capabilities' => 'array',
        'disabled_at' => 'datetime',
    ];

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
