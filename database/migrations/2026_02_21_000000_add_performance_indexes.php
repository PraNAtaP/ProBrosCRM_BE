<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index already exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return !empty($indexes);
    }

    /**
     * Safely add an index — skip if it already exists.
     */
    private function addIndexIfNotExists(string $table, array|string $columns, string $indexName): void
    {
        try {
            if (!$this->indexExists($table, $indexName)) {
                Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                    $t->index($columns, $indexName);
                });
            }
        } catch (\Throwable $e) {
            // Index already exists or table issue — log and continue
            echo "  ⚠ Skipping {$indexName}: {$e->getMessage()}\n";
        }
    }

    /**
     * Safely drop an index — skip if it does not exist.
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        try {
            if ($this->indexExists($table, $indexName)) {
                Schema::table($table, function (Blueprint $t) use ($indexName) {
                    $t->dropIndex($indexName);
                });
            }
        } catch (\Throwable $e) {
            echo "  ⚠ Skipping drop {$indexName}: {$e->getMessage()}\n";
        }
    }

    public function up(): void
    {
        // deals
        $this->addIndexIfNotExists('deals', ['status', 'user_id'], 'idx_deals_status_user');
        $this->addIndexIfNotExists('deals', 'created_at', 'idx_deals_created_at');

        // companies
        $this->addIndexIfNotExists('companies', 'area_id', 'idx_companies_area');
        $this->addIndexIfNotExists('companies', 'name', 'idx_companies_name');
        $this->addIndexIfNotExists('companies', 'industry', 'idx_companies_industry');

        // contacts
        $this->addIndexIfNotExists('contacts', 'email', 'idx_contacts_email');

        // activity_logs
        $this->addIndexIfNotExists('activity_logs', ['user_id', 'activity_type'], 'idx_activity_logs_user_type');
        $this->addIndexIfNotExists('activity_logs', 'contact_id', 'idx_activity_logs_contact');
        $this->addIndexIfNotExists('activity_logs', 'start_time', 'idx_activity_logs_start_time');

        // commissions
        $this->addIndexIfNotExists('commissions', 'status', 'idx_commissions_status');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('deals', 'idx_deals_status_user');
        $this->dropIndexIfExists('deals', 'idx_deals_created_at');

        $this->dropIndexIfExists('companies', 'idx_companies_area');
        $this->dropIndexIfExists('companies', 'idx_companies_name');
        $this->dropIndexIfExists('companies', 'idx_companies_industry');

        $this->dropIndexIfExists('contacts', 'idx_contacts_email');

        $this->dropIndexIfExists('activity_logs', 'idx_activity_logs_user_type');
        $this->dropIndexIfExists('activity_logs', 'idx_activity_logs_contact');
        $this->dropIndexIfExists('activity_logs', 'idx_activity_logs_start_time');

        $this->dropIndexIfExists('commissions', 'idx_commissions_status');
    }
};
