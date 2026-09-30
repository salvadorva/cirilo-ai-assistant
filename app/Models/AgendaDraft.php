<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Borrador de una acción de agenda por conversación (F2-04). */
class AgendaDraft extends Model
{
    protected $fillable = ['user_id', 'scope_key', 'action', 'data', 'missing', 'expires_at'];

    protected $casts = ['data' => 'array', 'missing' => 'array', 'expires_at' => 'datetime'];
}
