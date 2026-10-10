<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        Branch::create(['name' => 'Main Branch', 'address' => 'Addis Ababa', 'phone' => '0911000000']);

        $this->call(CatalogSeeder::class);

        // PART C — default expense categories + their GL accounts (idempotent).
        $this->call(ExpenseCategorySeeder::class);

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