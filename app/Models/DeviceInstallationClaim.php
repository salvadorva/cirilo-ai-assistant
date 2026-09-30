<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Registro de auditoría de reclamos/liberaciones de instalación (sin tokens). */
class DeviceInstallationClaim extends Model
{
    protected $fillable = ['installation_id', 'device_token_id', 'from_user_id', 'to_user_id', 'outcome', 'reason'];
}
