<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_activity_trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('activity_type', ['typing', 'english_games', 'tutor'])->index();
            $table->timestamp('session_start');
            $table->timestamp('session_end')->nullable();
            $table->integer('session_duration_seconds')->default(0);
            $table->integer('xp_earned')->default(0);
            $table->enum('activity_quality', ['micro', 'productive', 'excellent'])->default('productive');
            $table->json('session_data')->nullable(); // Para datos específicos del juego
            $table->boolean('completed')->default(false);
            $table->timestamps();

            // Índices para optimizar queries
            $table->index(['user_id', 'activity_type']);
            $table->index(['user_id', 'session_start']);
            $table->index(['activity_quality']);
            $table->index(['session_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_trackers');
    }
};
