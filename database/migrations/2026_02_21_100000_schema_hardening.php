<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    private function addSoftDeletesIfMissing(string $table): void
    {
        try {
            if (!$this->columnExists($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        } catch (\Throwable $e) {
            echo "  ⚠ Skipping soft deletes for {$table}: {$e->getMessage()}\n";
        }
    }

    public function up(): void
    {
        // Add SoftDeletes to tables that are missing it
        $this->addSoftDeletesIfMissing('contacts');
        $this->addSoftDeletesIfMissing('activity_logs');
        $this->addSoftDeletesIfMissing('commissions');
        $this->addSoftDeletesIfMissing('areas');
    }

    public function down(): void
    {
        $tables = ['contacts', 'activity_logs', 'commissions', 'areas'];
        foreach ($tables as $table) {
            try {
                if ($this->columnExists($table, 'deleted_at')) {
                    Schema::table($table, function (Blueprint $t) {
                        $t->dropSoftDeletes();
                    });
                }
            } catch (\Throwable $e) {
                echo "  ⚠ Skipping drop soft deletes for {$table}: {$e->getMessage()}\n";
            }
        }
    }
};
