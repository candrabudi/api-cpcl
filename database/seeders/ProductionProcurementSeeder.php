<?php

namespace Database\Seeders;

use App\Models\AnnualBudget;
use App\Models\AnnualBudgetTransaction;
use App\Models\Cooperative;
use App\Models\Item;
use App\Models\ItemTypeBudget;
use App\Models\PlenaryMeeting;
use App\Models\PlenaryMeetingItem;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\ProcurementItemProcessStatus;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionProcurementSeeder extends Seeder
{
    public function run(): void
    {
        try {
            DB::beginTransaction();

            $admin = User::whereIn('role', ['admin', 'superadmin'])->first();
            $cooperative = Cooperative::first();
            
            // Ensure Vendor ID 3 exists
            $vendor = Vendor::find(3);
            if (!$vendor) {
                $vendor = Vendor::create([
                    'id' => 3,
                    'name' => 'PT. Galangan Samudera Jaya',
                    'pic_name' => 'Bapak Irsyad',
                    'pic_phone' => '081233445566',
                    'address' => 'Kawasan Industri Perkapalan No. 45',
                    'status' => 'active',
                ]);
            }

            if (!$cooperative) {
                $cooperative = Cooperative::create([
                    'name' => 'Koperasi Nelayan Contoh',
                    'registration_number' => 'REG-' . time(),
                    'area_id' => 1,
                ]);
            }

            // 1. Create Plenary Meeting
            $meeting = PlenaryMeeting::create([
                'meeting_title' => 'Rapat Pleno Pengadaan Kapal & Alat Tangkap 2026',
                'meeting_date' => Carbon::now(),
                'meeting_time' => '09:00',
                'location' => 'Aula Serbaguna KKP',
                'chairperson' => 'Drs. Haji Mulyono',
                'secretary' => 'Siti Aminah, S.E.',
                'notes' => 'Pembahasan kebutuhan armada kapal dan alat tangkap strategis 2026',
                'created_by' => $admin?->id ?? 1,
            ]);

            // 2. Get items with process_type = production
            $productionItems = Item::where('process_type', 'production')->get();

            if ($productionItems->isEmpty()) {
                throw new \Exception("No production items found. Please run ItemProductionSeeder first.");
            }

            // 3. Add items to Plenary Meeting
            $meetingItems = [];
            foreach ($productionItems as $item) {
                $meetingItems[] = PlenaryMeetingItem::create([
                    'plenary_meeting_id' => $meeting->id,
                    'cooperative_id' => $cooperative->id,
                    'item_id' => $item->id,
                    'package_quantity' => rand(2, 5),
                    'note' => 'Kebutuhan mendesak untuk nelayan ' . $cooperative->name,
                    'location' => $cooperative->regency ?? 'Wilayah Pesisir',
                ]);
            }

            // 4. Create Procurement for Vendor ID 3
            $annualBudget = AnnualBudget::where('budget_year', 2026)->first();

            $procurement = Procurement::create([
                'vendor_id' => 3,
                'procurement_number' => 'PROC/PROD/' . Carbon::now()->format('Ymd') . '/' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                'procurement_date' => Carbon::now(),
                'notes' => 'Kontrak pembangunan kapal dan perakitan alat tangkap batch 1',
                'status' => 'processed',
                'annual_budget_id' => $annualBudget?->id,
                'created_by' => $admin?->id ?? 1,
            ]);

            // 5. Create Procurement Items
            foreach ($meetingItems as $mItem) {
                $quantity = $mItem->package_quantity;
                $unitPrice = rand(50000000, 500000000); // 50jt - 500jt
                $totalPrice = $quantity * $unitPrice;

                $procItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $mItem->id,
                    'plenary_meeting_id' => $meeting->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'delivery_status' => 'pending',
                    'process_status' => 'production',
                    'created_by' => $admin?->id ?? 1,
                ]);

                // Create initial production status log
                ProcurementItemProcessStatus::create([
                    'procurement_item_id' => $procItem->id,
                    'status' => 'pending',
                    'changed_by' => $admin?->id ?? 1,
                    'status_date' => Carbon::now()->format('Y-m-d'),
                    'notes' => 'Menunggu antrian produksi di galangan',
                ]);

                // Log Transaction (Deduct from Category Budget)
                $itemTypeBudget = ItemTypeBudget::where('item_type_id', $mItem->item->item_type_id)
                    ->where('year', 2026)
                    ->first();

                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget?->id,
                    'item_type_budget_id' => $itemTypeBudget?->id,
                    'procurement_item_id' => $procItem->id,
                    'type' => 'spending',
                    'amount' => $totalPrice,
                    'notes' => 'Spending for ' . $mItem->item->name . ' (Procurement: ' . $procurement->procurement_number . ')',
                ]);
            }

            DB::commit();
            $this->command->info('Production Plenary Meeting & Procurement created successfully for Vendor 3');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('Failed to seed production procurement: ' . $e->getMessage());
        }
    }
}
