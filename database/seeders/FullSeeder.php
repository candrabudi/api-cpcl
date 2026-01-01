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
        // Items (kapal & mesin kapal)
        // ===============================
        $itemIds = [];

        // 2 kapal
        $itemIds[] = DB::table('items')->insertGetId([
            'name' => 'Kapal Perikanan Nusantara 1',
            'type' => 'ship',
            'code' => 'SHIP001',
            'brand' => 'PT Kapalindo',
            'model' => 'KP-100',
            'specification' => 'Kapasitas 50 ton, mesin diesel 500HP',
            'unit' => 'unit',
            'weight' => 120.00,
            'length' => 30.00,
            'width' => 8.00,
            'height' => 10.00,
            'description' => 'Kapal untuk operasional penangkapan ikan di laut dalam.',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $itemIds[] = DB::table('items')->insertGetId([
            'name' => 'Kapal Perikanan Nusantara 2',
            'type' => 'ship',
            'code' => 'SHIP002',
            'brand' => 'PT Kapalindo',
            'model' => 'KP-200',
            'specification' => 'Kapasitas 75 ton, mesin diesel 700HP',
            'unit' => 'unit',
            'weight' => 150.00,
            'length' => 35.00,
            'width' => 9.00,
            'height' => 11.00,
            'description' => 'Kapal untuk operasional penangkapan ikan di perairan regional.',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // 2 mesin kapal
        $itemIds[] = DB::table('items')->insertGetId([
            'name' => 'Mesin Diesel Kapal 500HP',
            'type' => 'machine',
            'code' => 'ENG001',
            'brand' => 'Yanmar',
            'model' => 'YD500',
            'specification' => 'Mesin diesel 500HP untuk kapal 50-60 ton',
            'unit' => 'unit',
            'weight' => 5.5,
            'length' => 2.5,
            'width' => 1.5,
            'height' => 2.0,
            'description' => 'Mesin kapal untuk Kapal Perikanan Nusantara 1',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $itemIds[] = DB::table('items')->insertGetId([
            'name' => 'Mesin Diesel Kapal 700HP',
            'type' => 'machine',
            'code' => 'ENG002',
            'brand' => 'Yanmar',
            'model' => 'YD700',
            'specification' => 'Mesin diesel 700HP untuk kapal 70-80 ton',
            'unit' => 'unit',
            'weight' => 6.0,
            'length' => 3.0,
            'width' => 1.8,
            'height' => 2.2,
            'description' => 'Mesin kapal untuk Kapal Perikanan Nusantara 2',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // ===============================
        // Plenary Meetings
        // ===============================
        $meetingIds = [];
        for ($i = 1; $i <= 2; ++$i) {
            $meetingIds[] = DB::table('plenary_meetings')->insertGetId([
                'meeting_title' => "Rapat Pleno Kementerian Perikanan #$i",
                'meeting_date' => Carbon::today()->addDays($i),
                'meeting_time' => '09:00:00',
                'location' => 'Jakarta',
                'chairperson' => $faker->name,
                'secretary' => $faker->name,
                'notes' => 'Rapat membahas pengadaan kapal dan mesin kapal.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // ===============================
        // Plenary Meeting Items
        // ===============================
        $plenaryMeetingItemIds = [];
        $cooperativeIds = [2, 3, 4];
        foreach ($meetingIds as $meetingId) {
            foreach ($itemIds as $itemId) {
                $plenaryMeetingItemIds[] = DB::table('plenary_meeting_items')->insertGetId([
                    'plenary_meeting_id' => $meetingId,
                    'cooperative_id' => $faker->randomElement($cooperativeIds),
                    'cpcl_document_id' => null,
                    'item_id' => $itemId,
                    'package_quantity' => 2,
                    'note' => 'Pengadaan untuk operasional kapal',
                    'location' => 'Pelabuhan Jakarta',
                    'unit_price' => 5000000,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        // ===============================
        // Plenary Meeting Attendees
        // ===============================
        foreach ($meetingIds as $meetingId) {
            for ($i = 0; $i < 2; ++$i) {
                DB::table('plenary_meeting_attendees')->insert([
                    'plenary_meeting_id' => $meetingId,
                    'name' => $faker->name,
                    'work_unit' => 'Kementerian Perikanan',
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
        foreach ($meetingIds as $meetingId) {
            for ($i = 0; $i < 2; ++$i) {
                $procurementIds[] = DB::table('procurements')->insertGetId([
                    'plenary_meeting_id' => $meetingId,
                    'procurement_number' => strtoupper(Str::random(8)),
                    'procurement_date' => Carbon::today(),
                    'status' => 'draft',
                    'notes' => 'Pengadaan kapal dan mesin kapal',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        // ===============================
        // Procurement Items
        // ===============================
        $procurementItemIds = [];
        foreach ($procurementIds as $procurementId) {
            foreach ($plenaryMeetingItemIds as $pmItemId) {
                $procurementItemIds[] = DB::table('procurement_items')->insertGetId([
                    'procurement_id' => $procurementId,
                    'plenary_meeting_item_id' => $pmItemId,
                    'vendor_id' => 1,
                    'quantity' => 2,
                    'unit_price' => 5000000,
                    'total_price' => 2 * 5000000,
                    'delivery_status' => 'pending',
                    'process_status' => 'pending',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
    }
}
