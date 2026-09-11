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
        Schema::create('user_game_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('total_xp')->default(0);
            $table->integer('level')->default(1);
            $table->integer('xp_to_next_level')->default(100);
            $table->integer('typing_score')->default(0);
            $table->integer('tutor_score')->default(0);
            $table->integer('total_sessions')->default(0);
            $table->integer('total_time_played')->default(0); // en segundos
            $table->float('best_wpm')->default(0);
            $table->float('average_accuracy')->default(0);
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->json('weekly_stats')->nullable(); // estadísticas semanales
            $table->json('monthly_stats')->nullable(); // estadísticas mensuales
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['level', 'total_xp']);
            $table->index('last_activity_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_game_progress');
    }
};
