<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcurementSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $meetings = DB::table('plenary_meetings')->get();
            $vendors = DB::table('vendors')->pluck('id');
            $annualBudget = DB::table('annual_budgets')->first();

            foreach ($meetings as $meeting) {
                $procurementId = DB::table('procurements')->insertGetId([
                    'plenary_meeting_id' => $meeting->id,
                    'procurement_number' => 'PRC-'.strtoupper(Str::random(8)),
                    'procurement_date' => Carbon::now()->subDays(rand(1, 15))->toDateString(),
                    'status' => 'draft',
                    'notes' => 'Generated from seeder',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $meetingItems = DB::table('plenary_meeting_items')
                    ->where('plenary_meeting_id', $meeting->id)
                    ->limit(3)
                    ->get();

                foreach ($meetingItems as $meetingItem) {
                    $quantity = $meetingItem->package_quantity;
                    $unitPrice = $meetingItem->unit_price ?? rand(100000, 500000);
                    $totalPrice = $quantity * $unitPrice;
                    $vendorId = $vendors->random();

                    $procurementItemId = DB::table('procurement_items')->insertGetId([
                        'procurement_id' => $procurementId,
                        'plenary_meeting_item_id' => $meetingItem->id,
                        'vendor_id' => $vendorId,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'delivery_status' => 'pending',
                        'process_status' => 'pending',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    DB::table('procurement_item_status_logs')->insert([
                        'procurement_item_id' => $procurementItemId,
                        'old_delivery_status' => null,
                        'new_delivery_status' => 'pending',
                        'area_id' => null,
                        'status_date' => Carbon::now()->toDateString(),
                        'changed_by' => 1,
                        'notes' => 'Initial delivery status',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    DB::table('procurement_item_process_statuses')->insert([
                        'procurement_item_id' => $procurementItemId,
                        'status' => 'pending',
                        'production_start_date' => null,
                        'production_end_date' => null,
                        'area_id' => null,
                        'changed_by' => 1,
                        'notes' => 'Initial process status',
                        'status_date' => Carbon::now()->toDateString(),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    if ($annualBudget) {
                        DB::table('annual_budget_transactions')->insert([
                            'annual_budget_id' => $annualBudget->id,
                            'procurement_item_id' => $procurementItemId,
                            'amount' => $totalPrice,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                }
            }
        });
    }
}
