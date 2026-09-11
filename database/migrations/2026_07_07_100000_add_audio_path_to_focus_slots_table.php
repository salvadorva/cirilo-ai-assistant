<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('focus_slots', function (Blueprint $table) {
            // mp3 cacheado del mensaje (disk public); null = pendiente de generar
            $table->string('audio_path')->nullable()->after('with_audio');
        });
    }

    public function down(): void
    {
        Schema::table('focus_slots', function (Blueprint $table) {
            $table->dropColumn('audio_path');
        });
    }
};
