<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Area;
use App\Models\Commission;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Areas
        $malangKota = Area::create([
            'name' => 'Malang Kota',
            'description' => 'Area coverage for Malang City center and surroundings',
        ]);

        $batu = Area::create([
            'name' => 'Batu',
            'description' => 'Area coverage for Batu city and mountain resorts',
        ]);

        // Create Users
        $admin = User::create([
            'name' => 'Admin Pro Bros',
            'email' => 'admin@probros.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $rana = User::create([
            'name' => 'Rana',
            'email' => 'rana@probros.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SALES,
        ]);

        $budi = User::create([
            'name' => 'Budi',
            'email' => 'budi@probros.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SALES,
        ]);

        $sari = User::create([
            'name' => 'Sari',
            'email' => 'sari@probros.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SALES,
        ]);

        // Create Companies
        $companies = [
            [
                'area_id' => $malangKota->id,
                'name' => 'Bean There Cafe',
                'address' => 'Jl. Ijen No. 15, Malang',
                'industry' => 'Coffee Shop',
                'phone' => '0341-123456',
            ],
            [
                'area_id' => $malangKota->id,
                'name' => 'Latte Art Studio',
                'address' => 'Jl. Kawi No. 8, Malang',
                'industry' => 'Coffee Shop',
                'phone' => '0341-234567',
            ],
            [
                'area_id' => $batu->id,
                'name' => 'Sweet Tooth Bakery',
                'address' => 'Jl. Selecta No. 22, Batu',
                'industry' => 'Bakery',
                'phone' => '0341-345678',
            ],
            [
                'area_id' => $malangKota->id,
                'name' => 'New Wave Coffee',
                'address' => 'Jl. Soekarno Hatta No. 100, Malang',
                'industry' => 'Coffee Shop',
                'phone' => '0341-456789',
            ],
            [
                'area_id' => $batu->id,
                'name' => 'Morning Brew',
                'address' => 'Jl. Panderman No. 5, Batu',
                'industry' => 'Restaurant',
                'phone' => '0341-567890',
            ],
            [
                'area_id' => $malangKota->id,
                'name' => 'Spiced Life',
                'address' => 'Jl. Bandung No. 12, Malang',
                'industry' => 'Restaurant',
                'phone' => '0341-678901',
            ],
            [
                'area_id' => $batu->id,
                'name' => 'Daily Grind',
                'address' => 'Jl. Brantas No. 7, Batu',
                'industry' => 'Coffee Shop',
                'phone' => '0341-789012',
            ],
            [
                'area_id' => $malangKota->id,
                'name' => 'Flavor Lab',
                'address' => 'Jl. Jakarta No. 45, Malang',
                'industry' => 'Test Kitchen',
                'phone' => '0341-890123',
            ],
        ];

        foreach ($companies as $companyData) {
            $company = Company::create($companyData);

            // Create a contact for each company
            Contact::create([
                'company_id' => $company->id,
                'name' => 'Manager ' . $company->name,
                'email' => strtolower(str_replace(' ', '', $company->name)) . '@email.com',
                'phone' => $company->phone,
                'position' => 'Purchasing Manager',
            ]);
        }

        // Get all contacts
        $contacts = Contact::all();

        // Create Deals matching the original dummy data
        $deals = [
            ['contact_id' => 1, 'user_id' => $rana->id, 'title' => 'Annual Coffee Supply', 'value' => 1200, 'status' => Deal::STATUS_LEAD],
            ['contact_id' => 2, 'user_id' => $budi->id, 'title' => 'Premium Milk Contract', 'value' => 850, 'status' => Deal::STATUS_LEAD],
            ['contact_id' => 3, 'user_id' => $rana->id, 'title' => 'Bulk Sugar Order', 'value' => 4200, 'status' => Deal::STATUS_CONTACTED],
            ['contact_id' => 4, 'user_id' => $sari->id, 'title' => 'Espresso Machines', 'value' => 4500, 'status' => Deal::STATUS_QUALIFIED],
            ['contact_id' => 5, 'user_id' => $rana->id, 'title' => 'Syrup Restock', 'value' => 2100, 'status' => Deal::STATUS_QUOTES_SENT],
            ['contact_id' => 6, 'user_id' => $budi->id, 'title' => 'Chai Concentrate', 'value' => 1800, 'status' => Deal::STATUS_TRIAL_ORDER],
            ['contact_id' => 7, 'user_id' => $rana->id, 'title' => 'Q1 Bean Supply', 'value' => 3500, 'status' => Deal::STATUS_ACTIVE_CUSTOMER],
            ['contact_id' => 8, 'user_id' => $sari->id, 'title' => 'Test Kitchen Setup', 'value' => 1500, 'status' => Deal::STATUS_LOST_CUSTOMER],
        ];

        foreach ($deals as $dealData) {
            $deal = Deal::create(array_merge($dealData, [
                'description' => 'Deal for ' . $dealData['title'],
            ]));

            // Create an initial activity log
            ActivityLog::create([
                'deal_id' => $deal->id,
                'user_id' => $deal->user_id,
                'activity_type' => ActivityLog::TYPE_NOTE,
                'notes' => 'Deal created',
            ]);
        }

        // Note: Commission for 'Q1 Bean Supply' will be auto-created by the DealObserver
        // since it's created with status = active_customer

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin login: admin@probros.com / password');
        $this->command->info('Sales login: rana@probros.com / password');
    }
}
