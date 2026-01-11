<?php

namespace Database\Seeders;

use App\Models\AnnualBudget;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\ItemTypeBudget;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemProductionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Annual Budget for 2026 exists
        $annualBudget = AnnualBudget::firstOrCreate(
            ['budget_year' => 2026],
            [
                'total_budget' => 10000000000, // 10 Billion
                'used_budget' => 0,
                'allocated_budget' => 0,
                'remaining_budget' => 10000000000,
            ]
        );

        // 2. Define Production-based Categories (Item Types)
        $categories = [
            'Fabrikasi Kapal' => 5000000000,    // 5 Billion
            'Alat Penangkapan Ikan' => 2000000000, // 2 Billion
            'Unit Pengolahan Ikan' => 1500000000,  // 1.5 Billion
        ];

        // 3. Define Items that require Production Process
        $productionItems = [
            'Fabrikasi Kapal' => [
                ['name' => 'Kapal Penangkap Ikan 5 GT', 'unit' => 'Unit', 'desc' => 'Pembangunan kapal kayu/fiberglass 5 GT'],
                ['name' => 'Kapal Penangkap Ikan 10 GT', 'unit' => 'Unit', 'desc' => 'Pembangunan kapal kayu/fiberglass 10 GT'],
            ],
            'Alat Penangkapan Ikan' => [
                ['name' => 'Jaring Pursein Custom', 'unit' => 'Set', 'desc' => 'Perakitan jaring pursein sesuai spesifikasi'],
                ['name' => 'Sero / Jermal', 'unit' => 'Unit', 'desc' => 'Konstruksi alat tangkap menetap'],
            ],
            'Unit Pengolahan Ikan' => [
                ['name' => 'Cold Storage Portable 10T', 'unit' => 'Unit', 'desc' => 'Fabrikasi unit pendingin portable'],
                ['name' => 'Mesin Pengasap Ikan', 'unit' => 'Unit', 'desc' => 'Fabrikasi alat pengasapan semi-modern'],
            ],
        ];

        $admin = User::where('role', 'admin')->orWhere('role', 'superadmin')->first();

        foreach ($categories as $typeName => $budgetAmount) {
            // Create Item Type
            $itemType = ItemType::firstOrCreate(['name' => $typeName]);

            // Create Item Type Budget for 2026
            // This will trigger the AnnualBudgetTransaction via booted() in Model
            ItemTypeBudget::updateOrCreate(
                [
                    'item_type_id' => $itemType->id,
                    'year' => 2026,
                ],
                ['amount' => $budgetAmount]
            );

            // Create Production Items
            if (isset($productionItems[$typeName])) {
                foreach ($productionItems[$typeName] as $row) {
                    Item::updateOrCreate(
                        ['name' => $row['name']],
                        [
                            'item_type_id' => $itemType->id,
                            'unit' => $row['unit'],
                            'description' => $row['desc'],
                            'process_type' => 'production', // Mandatory as requested
                            'created_by' => $admin?->id,
                        ]
                    );
                }
            }
        }
    }
}
