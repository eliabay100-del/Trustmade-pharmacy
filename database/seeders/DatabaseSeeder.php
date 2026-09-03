<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\DrugCategory;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\ProductCategory;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        Branch::create(['name' => 'Main Branch', 'address' => 'Addis Ababa', 'phone' => '0911000000']);

        DrugCategory::create(['name' => 'Antibiotic']);
        DrugCategory::create(['name' => 'Analgesic']);
        DrugCategory::create(['name' => 'Antimalarial']);

        foreach (['Cosmetics', 'Hair Care', 'Skin Care', 'Oral Care', 'Personal Care', 'Baby Care', 'Hygiene Products', 'Medical Supplies', 'Other Products'] as $name) {
            ProductCategory::create(['name' => $name, 'active' => true]);
        }

        $accounts = [
            ['name' => 'Cash', 'code' => '1001', 'type' => 'asset'],
            ['name' => 'Inventory', 'code' => '1200', 'type' => 'asset'],
            ['name' => 'Accounts Payable', 'code' => '2000', 'type' => 'liability'],
            ['name' => 'Owner Capital', 'code' => '3000', 'type' => 'equity'],
            ['name' => 'Sales Revenue', 'code' => '4000', 'type' => 'revenue'],
            ['name' => 'Cost of Goods Sold', 'code' => '5000', 'type' => 'expense'],
        ];
        foreach ($accounts as $acc) { Account::create($acc); }

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@pmas.com',
            'password' => Hash::make('password'),
            'branch_id' => 1,
            'role' => 'owner_admin'
        ]);
    }
}