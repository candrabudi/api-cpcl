<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            // Core System Data
            UsersTableSeeder::class,
            
            // Master Data
            AreaSeeder::class,
            DocumentTypeSeeder::class,
            
            // Dynamic Forms
            GroupFieldSeeder::class,
            
            // Budgeting System
            AnnualBudgetSeeder::class,
            BudgetUsageSeeder::class,
        ]);
    }
}
