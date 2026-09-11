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
        Schema::create('english_game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('game_type'); // word-match, sentence-builder, vocabulary-shooter, etc.
            $table->integer('score')->default(0);
            $table->decimal('accuracy', 5, 2)->default(0); // Porcentaje de aciertos
            $table->integer('time_spent')->default(0); // Segundos
            $table->integer('correct_answers')->default(0);
            $table->integer('total_questions')->default(0);
            $table->integer('level')->default(1); // Nivel del usuario en el momento del juego
            $table->timestamps();

            // Índices para mejorar rendimiento
            $table->index(['user_id', 'game_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('english_game_sessions');
    }
};
