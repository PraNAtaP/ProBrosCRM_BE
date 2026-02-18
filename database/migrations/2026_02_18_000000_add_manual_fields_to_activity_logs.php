<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->enum('meeting_type', ['Online', 'Offline'])->nullable()->after('activity_type');
            $table->dateTime('start_time')->nullable()->after('meeting_type');
            $table->unsignedInteger('duration')->nullable()->after('start_time');
        });

        // Make deal_id nullable
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('deal_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['meeting_type', 'start_time', 'duration']);
        });

        // Revert deal_id to non-nullable
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('deal_id')->nullable(false)->change();
        });
    }
};
