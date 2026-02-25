<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add trading_name to companies
        if (!Schema::hasColumn('companies', 'trading_name')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('trading_name')->nullable()->after('name');
            });
        }

        // 2. Create pivot table
        if (!Schema::hasTable('contact_company')) {
            Schema::create('contact_company', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade');
                $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['contact_id', 'company_id']);
            });
        }

        // 3. Migrate existing company_id relationships into pivot
        if (Schema::hasColumn('contacts', 'company_id')) {
            $existing = DB::table('contacts')
                ->whereNotNull('company_id')
                ->select('id', 'company_id')
                ->get();

            foreach ($existing as $contact) {
                DB::table('contact_company')->insertOrIgnore([
                    'contact_id' => $contact->id,
                    'company_id' => $contact->company_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_company');

        if (Schema::hasColumn('companies', 'trading_name')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('trading_name');
            });
        }
    }
};
