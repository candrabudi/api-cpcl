<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FullSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // ===============================
        // Items
        // ===============================
        $itemIds = [];
        $itemTypes = ['machine', 'equipment', 'goods', 'ship', 'other'];

        for ($i = 0; $i < 10; ++$i) {
            $itemIds[] = DB::table('items')->insertGetId([
                'name' => $faker->word.' '.$i,
                'type' => $faker->randomElement($itemTypes),
                'code' => strtoupper(Str::random(6)),
                'brand' => $faker->company,
                'model' => $faker->bothify('Model-###'),
                'specification' => $faker->sentence,
                'unit' => $faker->randomElement(['pcs', 'set', 'kg', 'ltr']),
                'weight' => $faker->randomFloat(2, 1, 100),
                'length' => $faker->randomFloat(2, 1, 100),
                'width' => $faker->randomFloat(2, 1, 100),
                'height' => $faker->randomFloat(2, 1, 100),
                'description' => $faker->paragraph,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // ===============================
        // Plenary Meetings
        // ===============================
        $meetingIds = [];
        for ($i = 0; $i < 3; ++$i) {
            $meetingIds[] = DB::table('plenary_meetings')->insertGetId([
                'meeting_title' => 'Plenary Meeting '.($i + 1),
                'meeting_date' => $faker->date(),
                'meeting_time' => $faker->time(),
                'location' => $faker->city,
                'chairperson' => $faker->name,
                'secretary' => $faker->name,
                'notes' => $faker->sentence,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // ===============================
        // Plenary Meeting Items
        // ===============================
        $plenaryMeetingItemIds = [];
        $cooperativeIds = [1, 2, 3];
        foreach ($meetingIds as $meetingId) {
            foreach ($itemIds as $itemId) {
                $plenaryMeetingItemIds[] = DB::table('plenary_meeting_items')->insertGetId([
                    'plenary_meeting_id' => $meetingId,
                    'cooperative_id' => $faker->randomElement($cooperativeIds),
                    'cpcl_document_id' => null,
                    'item_id' => $itemId,
                    'package_quantity' => $faker->numberBetween(1, 50),
                    'note' => $faker->sentence,
                    'location' => $faker->city,
                    'unit_price' => $faker->randomFloat(2, 1000, 100000),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        // ===============================
        // Plenary Meeting Attendees
        // ===============================
        foreach ($meetingIds as $meetingId) {
            for ($i = 0; $i < 3; ++$i) {
                DB::table('plenary_meeting_attendees')->insert([
                    'plenary_meeting_id' => $meetingId,
                    'name' => $faker->name,
                    'work_unit' => $faker->company,
                    'position' => $faker->jobTitle,
                    'signature' => null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        // ===============================
        // Annual Budget
        // ===============================
        $budgetId = DB::table('annual_budgets')->insertGetId([
            'budget_year' => 2026,
            'total_budget' => 5000000000,
            'used_budget' => 0,
            'remaining_budget' => 5000000000,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // ===============================
        // Procurements
        // ===============================
        $procurementIds = [];
        $statuses = ['draft', 'approved', 'contracted', 'in_progress', 'completed', 'cancelled'];
        foreach ($meetingIds as $meetingId) {
            $procurementIds[] = DB::table('procurements')->insertGetId([
                'plenary_meeting_id' => $meetingId,
                'procurement_number' => strtoupper(Str::random(8)),
                'procurement_date' => $faker->date(),
                'status' => $faker->randomElement($statuses),
                'notes' => $faker->sentence,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // ===============================
        // Procurement Items
        // ===============================
        $procurementItemIds = [];
        foreach ($procurementIds as $procurementId) {
            foreach ($plenaryMeetingItemIds as $pmItemId) {
                $quantity = $faker->numberBetween(1, 100);
                $unitPrice = $faker->randomFloat(2, 1000, 10000);
                $procurementItemIds[] = DB::table('procurement_items')->insertGetId([
                    'procurement_id' => $procurementId,
                    'plenary_meeting_item_id' => $pmItemId,
                    'vendor_id' => 1, // Tetap 1
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $quantity * $unitPrice,
                    'delivery_status' => 'pending',
                    'process_status' => 'pending',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        // ===============================
        // Procurement Item Process Statuses
        // ===============================
        $processStatuses = ['pending', 'purchase', 'production', 'completed'];
        foreach ($procurementItemIds as $procItemId) {
            DB::table('procurement_item_process_statuses')->insert([
                'procurement_item_id' => $procItemId,
                'status' => $faker->randomElement($processStatuses),
                'production_start_date' => $faker->date(),
                'production_end_date' => $faker->date(),
                'area_id' => null,
                'changed_by' => 1,
                'notes' => $faker->sentence,
                'status_date' => $faker->date(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // ===============================
        // Procurement Item Status Logs
        // ===============================
        foreach ($procurementItemIds as $procItemId) {
            DB::table('procurement_item_status_logs')->insert([
                'procurement_item_id' => $procItemId,
                'old_delivery_status' => null,
                'new_delivery_status' => 'pending',
                'area_id' => null,
                'status_date' => $faker->date(),
                'changed_by' => 1,
                'notes' => $faker->sentence,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // ===============================
        // Annual Budget Transactions
        // ===============================
        foreach ($procurementItemIds as $procItemId) {
            DB::table('annual_budget_transactions')->insert([
                'annual_budget_id' => $budgetId,
                'procurement_item_id' => $procItemId,
                'amount' => $faker->randomFloat(2, 1000, 50000),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
