<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserEnglishLevel extends Model
{
    protected $fillable = [
        'user_id',
        'level',
        'score',
        'section_scores',
        'answers',
        'evaluated_at',
    ];

    protected $casts = [
        'section_scores' => 'array',
        'answers' => 'array',
        'evaluated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
