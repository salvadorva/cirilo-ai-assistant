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
        Schema::create('daily_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('activity_date');
            $table->enum('activity_type', ['typing', 'tutor', 'general']); // tipo de actividad
            $table->integer('sessions_count')->default(1); // número de sesiones ese día
            $table->integer('minutes_spent')->default(0); // minutos gastados
            $table->integer('xp_earned')->default(0); // XP ganado ese día
            $table->json('details')->nullable(); // detalles específicos de la actividad
            $table->timestamps();

            $table->unique(['user_id', 'activity_date', 'activity_type']);
            $table->index(['user_id', 'activity_date']);
            $table->index(['activity_type', 'activity_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_streaks');
    }
};
