<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent: re-running on a database that already has data updates
     * nothing and creates no duplicates.
     */
    public function run()
    {
        $branch = Branch::firstOrCreate(
            ['name' => 'Main Branch'],
            ['address' => 'Addis Ababa', 'phone' => '0911000000']
        );

        $this->call(CatalogSeeder::class);

        // Chart of accounts the posting code expects (idempotent, keyed by code).
        $this->call(AccountSeeder::class);

        // PART C — default expense categories + their GL accounts (idempotent).
        $this->call(ExpenseCategorySeeder::class);

        // PART D — business name / colors / receipt text for the first client
        // (idempotent: only fills settings the owner has not changed).
        $this->call(BrandingSeeder::class);

        User::firstOrCreate(
            ['email' => 'admin@pmas.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'branch_id' => $branch->id,
                'role' => 'owner_admin',
            ]
        );
    }
}