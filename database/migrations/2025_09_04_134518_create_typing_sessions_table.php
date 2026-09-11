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
        Schema::create('typing_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('mode')->default('practice'); // practice, lessons, competitive
            $table->string('level')->default('beginner'); // beginner, intermediate, advanced
            $table->string('theme')->nullable(); // general, tech, literature, etc.
            $table->text('text_used'); // El texto que se usó para la práctica
            $table->integer('wpm')->default(0); // Words per minute
            $table->decimal('accuracy', 5, 2)->default(0); // Precisión en porcentaje
            $table->integer('errors')->default(0); // Cantidad de errores
            $table->integer('time_seconds')->default(0); // Tiempo total en segundos
            $table->integer('total_keystrokes')->default(0); // Total de teclas presionadas
            $table->integer('correct_keystrokes')->default(0); // Teclas correctas
            $table->integer('xp_earned')->default(0); // XP ganado en esta sesión
            $table->json('error_analysis')->nullable(); // Análisis de errores en JSON
            $table->boolean('completed')->default(true); // Si completó toda la sesión
            $table->timestamp('session_date')->useCurrent();
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['user_id', 'session_date']);
            $table->index(['mode', 'level']);
            $table->index('wpm');
            $table->index('accuracy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('typing_sessions');
    }
};
