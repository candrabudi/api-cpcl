<?php

namespace Database\Seeders;

use App\Models\AnnualBudget;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\ItemTypeBudget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnnualBudgetSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create or Update Annual Budget 2026
        $totalBudget = 5000000000; // 5 Billion
        $annualBudget = AnnualBudget::updateOrCreate(
            ['budget_year' => 2026],
            [
                'total_budget' => $totalBudget,
                'used_budget' => 0,
                'remaining_budget' => $totalBudget,
            ]
        );

        // 2. Define Item Types and their Budgets for 2026
        $categories = [
            'Mesin Kapal' => 2000000000,
            'Alat Tangkap' => 1500000000,
            'Sarana Pendukung' => 1000000000,
            'Logistik & Operasional' => 500000000,
        ];

        // 3. Define Items per Item Type
        $itemsData = [
            'Mesin Kapal' => [
                ['name' => 'Mesin Tempel 15 PK', 'unit' => 'Unit'],
                ['name' => 'Mesin Inboard 30 PK', 'unit' => 'Unit'],
                ['name' => 'Genset Kapal 5KW', 'unit' => 'Unit'],
            ],
            'Alat Tangkap' => [
                ['name' => 'Jaring Gillnet', 'unit' => 'Set'],
                ['name' => 'Pancing Rawe', 'unit' => 'Set'],
                ['name' => 'Bubu Lipat', 'unit' => 'Pcs'],
            ],
            'Sarana Pendukung' => [
                ['name' => 'Coolbox 200L', 'unit' => 'Pcs'],
                ['name' => 'Life Jacket Standard', 'unit' => 'Pcs'],
                ['name' => 'GPS Marine Navigator', 'unit' => 'Unit'],
            ],
            'Logistik & Operasional' => [
                ['name' => 'Bahan Bakar Solar', 'unit' => 'Liter'],
                ['name' => 'Es Balok', 'unit' => 'Balok'],
            ],
        ];

        foreach ($categories as $typeName => $budgetAmount) {
            // Create/Update Item Type
            $itemType = ItemType::updateOrCreate(['name' => $typeName]);

            // Create/Update Item Type Budget for 2026
            ItemTypeBudget::updateOrCreate(
                [
                    'item_type_id' => $itemType->id,
                    'year' => 2026,
                ],
                ['amount' => $budgetAmount]
            );

            // Create Items for this type
            if (isset($itemsData[$typeName])) {
                foreach ($itemsData[$typeName] as $itemRow) {
                    Item::updateOrCreate(
                        ['name' => $itemRow['name']],
                        [
                            'item_type_id' => $itemType->id,
                            'unit' => $itemRow['unit'],
                            'description' => 'Standard equipment for ' . $typeName,
                        ]
                    );
                }
            }
        }
    }
}
