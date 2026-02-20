<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->index(['status', 'user_id'], 'idx_deals_status_user');
            $table->index('created_at', 'idx_deals_created_at');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->index('area_id', 'idx_companies_area');
            $table->index('name', 'idx_companies_name');
            $table->index('industry', 'idx_companies_industry');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index('email', 'idx_contacts_email');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['user_id', 'activity_type'], 'idx_activity_logs_user_type');
            $table->index('contact_id', 'idx_activity_logs_contact');
            $table->index('start_time', 'idx_activity_logs_start_time');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->index('status', 'idx_commissions_status');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex('idx_deals_status_user');
            $table->dropIndex('idx_deals_created_at');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_companies_area');
            $table->dropIndex('idx_companies_name');
            $table->dropIndex('idx_companies_industry');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_email');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_activity_logs_user_type');
            $table->dropIndex('idx_activity_logs_contact');
            $table->dropIndex('idx_activity_logs_start_time');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropIndex('idx_commissions_status');
        });
    }
};
