<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Emergency cleanup: drop any duplicate indexes that were partially
 * created before the idempotent migration fix.
 * Safe to run multiple times — checks before dropping.
 */
return new class extends Migration
{
    private function dropIndexSafely(string $table, string $indexName): void
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            if (!empty($indexes)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
                Log::info("Dropped duplicate index: {$table}.{$indexName}");
            }
        } catch (\Throwable $e) {
            Log::warning("Cleanup skip {$table}.{$indexName}: {$e->getMessage()}");
        }
    }

    public function up(): void
    {
        // Clean up any partially-created indexes from failed previous migration
        $this->dropIndexSafely('deals', 'idx_deals_status_user');
        $this->dropIndexSafely('deals', 'idx_deals_created_at');
        $this->dropIndexSafely('companies', 'idx_companies_area');
        $this->dropIndexSafely('companies', 'idx_companies_name');
        $this->dropIndexSafely('companies', 'idx_companies_industry');
        $this->dropIndexSafely('contacts', 'idx_contacts_email');
        $this->dropIndexSafely('activity_logs', 'idx_activity_logs_user_type');
        $this->dropIndexSafely('activity_logs', 'idx_activity_logs_contact');
        $this->dropIndexSafely('activity_logs', 'idx_activity_logs_start_time');
        $this->dropIndexSafely('commissions', 'idx_commissions_status');

        // Also clean the failed migration record so it can re-run cleanly
        DB::table('migrations')
            ->where('migration', '2026_02_21_000000_add_performance_indexes')
            ->delete();
    }

    public function down(): void
    {
        // No reverse needed — the main migration will re-add indexes
    }
};
