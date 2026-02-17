<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Disable constraints mostly for the truncation
        Schema::disableForeignKeyConstraints();

        // 2. Truncate Areas
        // Note: Since companies.area_id is NOT NULL, we cannot simply set it to NULL.
        // We will truncate companies relationships by assigning them to a temporary ID or just ignore for now 
        // because we are about to re-seed areas. 
        // HOWEVER, if we want to keep companies, we must assign them to a valid area ID.
        // New strategy: Create a temporary "Unassigned" area, move all companies there, then truncate others.
        // OR: Since this is a migration request to "replace" areas, and the user said "reset or empty area_id",
        // but the DB schema forbids NULL. We must update the schema or assign a default.
        
        // Let's actually modifying the column to be nullable first if possible, OR
        // simpler approach: Create the first new area, assign all companies to it, then delete old areas.
        
        $areas = [
            'AIRPORT', 'ASHFIELD', 'BONDI', 'CANBERRA', 'CITY 1', 'CITY 2', 'CITY 3', 
            'EARLY', 'INNER WEST', 'MANLY', 'MARRICKVILLE', 'NORTH SYDNEY', 
            'NORTHERN BEACH', 'PARRAMATTA', 'PATONGA', 'SOUTH', 'SURRY HILL', 
            'WAREHOUSE PICKUP', 'WEST'
        ];

        // Create the first area to hold existing companies
        $defaultAreaName = $areas[0];
        // Check if exists or create
        $defaultArea = Area::firstOrCreate(['name' => $defaultAreaName], ['description' => 'Default Area']);

        $this->command->info("Assigning all existing companies to default area: $defaultAreaName");
        Company::query()->update(['area_id' => $defaultArea->id]);

        // Now safe to delete all OTHER areas
        Area::where('id', '!=', $defaultArea->id)->delete();
        
        // Now fill the rest
        foreach ($areas as $areaName) {
            if ($areaName === $defaultAreaName) continue;
            
            Area::firstOrCreate([
                'name' => $areaName
            ], [
                'description' => 'Distribution area for ' . $areaName
            ]);
        }

        Schema::enableForeignKeyConstraints();

        $this->command->info('Successfully migrated ' . count($areas) . ' Australian areas.');
        $this->command->warn('All existing companies have been moved to: ' . $defaultAreaName);
    }
}
