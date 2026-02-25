<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalesOrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $companies = Company::all();
        $contacts = Contact::all();

        if ($users->isEmpty() || $companies->isEmpty()) {
            $this->command->warn('Need users and companies first. Run other seeders.');
            return;
        }

        $statuses = SalesOrder::STATUSES;
        $year = now()->year;

        $orders = [
            ['total' => 2500.00, 'status' => 'pending', 'fav' => true, 'notes' => 'Rush order — needs priority handling'],
            ['total' => 8750.50, 'status' => 'processing', 'fav' => false, 'notes' => null],
            ['total' => 1200.00, 'status' => 'processing', 'fav' => false, 'notes' => 'Customer requested specific brand'],
            ['total' => 15000.00, 'status' => 'delivered', 'fav' => true, 'notes' => null],
            ['total' => 3200.75, 'status' => 'delivered', 'fav' => false, 'notes' => 'Delivered early — client happy'],
            ['total' => 4500.00, 'status' => 'delivered', 'fav' => false, 'notes' => null],
            ['total' => 950.00, 'status' => 'cancelled', 'fav' => false, 'notes' => 'Client changed their mind'],
            ['total' => 6700.00, 'status' => 'cancelled', 'fav' => false, 'notes' => 'Stock unavailable'],
            ['total' => 2100.00, 'status' => 'pending', 'fav' => false, 'notes' => null, 'second' => true],
            ['total' => 3800.00, 'status' => 'processing', 'fav' => true, 'notes' => 'Check pricing with supplier'],
            ['total' => 11200.00, 'status' => 'pending', 'fav' => false, 'notes' => null, 'sync' => true],
            ['total' => 750.00, 'status' => 'processing', 'fav' => false, 'notes' => null, 'sync' => true],
            ['total' => 5400.00, 'status' => 'delivered', 'fav' => true, 'notes' => 'Repeat customer — give discount next time'],
            ['total' => 9800.00, 'status' => 'pending', 'fav' => false, 'notes' => null, 'second' => true],
            ['total' => 1850.00, 'status' => 'processing', 'fav' => false, 'notes' => 'Awaiting warehouse confirmation'],
            ['total' => 4200.00, 'status' => 'delivered', 'fav' => false, 'notes' => null],
            ['total' => 7600.00, 'status' => 'pending', 'fav' => false, 'notes' => null],
            ['total' => 2980.00, 'status' => 'processing', 'fav' => false, 'notes' => null, 'sync' => true],
            ['total' => 13500.00, 'status' => 'delivered', 'fav' => true, 'notes' => 'Large order — VIP treatment'],
            ['total' => 1100.00, 'status' => 'cancelled', 'fav' => false, 'notes' => 'Duplicate order'],
        ];

        foreach ($orders as $i => $order) {
            $company = $companies->random();
            $contact = $contacts->where('company_id', $company->id)->first() ?? $contacts->random();

            SalesOrder::create([
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'user_id' => $users->random()->id,
                'order_number' => sprintf('SO-%d-%04d', $year, $i + 1),
                'status' => $order['status'],
                'total_amount' => $order['total'],
                'is_favorite' => $order['fav'],
                'is_second_run' => $order['second'] ?? false,
                'needs_price_sync' => $order['sync'] ?? false,
                'additional_notes' => $order['notes'],
                'delivery_date' => $order['status'] === 'delivered' ? now()->subDays(rand(1, 14)) : now()->addDays(rand(1, 14)),
                'delivered_at' => $order['status'] === 'delivered' ? now()->subDays(rand(1, 7)) : null,
                'cancelled_at' => $order['status'] === 'cancelled' ? now()->subDays(rand(1, 5)) : null,
            ]);
        }

        $this->command->info('Created 20 sample sales orders.');
    }
}
