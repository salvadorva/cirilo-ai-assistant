<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaticAudio extends Model
{
    use HasFactory;

    protected $table = 'static_audios';

    protected $fillable = [
        'type',
        'text',
        'file_path',
        'index',
        'is_approved',
        'is_active',
        'regeneration_count',
        'last_regenerated_at',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_active' => 'boolean',
        'last_regenerated_at' => 'datetime',
    ];

    /**
     * Scope para obtener solo audios aprobados
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope para obtener solo audios activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para obtener audios por tipo
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Obtener la URL completa del archivo
     */
    public function getAudioUrlAttribute()
    {
        if (! $this->file_path) {
            return null;
        }

        return \Storage::disk('public')->url($this->file_path);
    }

    /**
     * Verificar si el archivo existe
     */
    public function audioFileExists()
    {
        if (! $this->file_path) {
            return false;
        }

        return \Storage::disk('public')->exists($this->file_path);
    }
}
