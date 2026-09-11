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
        Schema::create('user_english_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('level', 5); // A1, A2, B1, B2, C1, C2
            $table->integer('score');
            $table->json('section_scores')->nullable(); // Detalles por sección
            $table->json('answers')->nullable(); // Respuestas dadas por el usuario
            $table->timestamp('evaluated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_english_levels');
    }
};
