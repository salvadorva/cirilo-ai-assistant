<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Clave de memoria olvidada (F4-06). No guarda el valor olvidado. */
class UserProfileFactTombstone extends Model
{
    public const REASON_USER = 'user';

    public const REASON_EXTRACTION = 'extraction';

    protected $fillable = ['user_id', 'category', 'key', 'reason', 'forgotten_at'];

    protected $casts = ['forgotten_at' => 'datetime'];
}
