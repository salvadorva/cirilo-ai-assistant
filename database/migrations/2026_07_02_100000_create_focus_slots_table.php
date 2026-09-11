<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focus_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('time', 5); // 'HH:MM' hora local America/Guatemala
            $table->json('days'); // ISO weekdays: 1=lunes ... 7=domingo
            $table->string('title');
            $table->text('message');
            $table->string('voice', 20)->default('echo');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focus_slots');
    }
};
