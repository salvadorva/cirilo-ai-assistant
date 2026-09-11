<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->json('topics')->nullable()->after('summarized_message_count');
            $table->json('decisions')->nullable()->after('topics');
            $table->json('pending_items')->nullable()->after('decisions');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['topics', 'decisions', 'pending_items']);
        });
    }
};
