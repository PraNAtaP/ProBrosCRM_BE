<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('deal_id')->constrained('contacts')->onDelete('set null');
            $table->foreignId('company_id')->nullable()->after('contact_id')->constrained('companies')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['company_id']);
            $table->dropColumn(['contact_id', 'company_id']);
        });
    }
};
