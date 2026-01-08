<?php

namespace Database\Seeders;

use App\Models\AnnualBudget;
use App\Models\AnnualBudgetTransaction;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\ItemTypeBudget;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\Vendor;
use App\Models\User;
use App\Models\Area;
use App\Models\Cooperative;
use App\Models\PlenaryMeeting;
use App\Models\PlenaryMeetingItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BudgetUsageSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Ambil Data Master
            $budget = AnnualBudget::where('budget_year', 2026)->first();
            $itemType = ItemType::where('name', 'Mesin Kapal')->first();
            $itemTypeBudget = ItemTypeBudget::where('item_type_id', $itemType->id)->where('year', 2026)->first();
            $item = Item::where('item_type_id', $itemType->id)->first();
            $admin = User::where('role', 'admin')->first();
            $area = Area::first();

            // 2. Buat Koperasi Contoh
            $cooperative = Cooperative::create([
                'area_id' => $area->id,
                'name' => 'Koperasi Nelayan Maju Jaya',
                'street_address' => 'Pesisir Pantai Selatan', // Sesuai kolom di migrasi
                'chairman_name' => 'Haji Lulung', // Sesuai kolom di migrasi
                'registration_number' => 'REG-001-2026'
            ]);

            // 3. Buat User & Vendor
            $vendorUser = User::create([
                'username' => 'bahariteknik',
                'email' => 'sales@bahariteknik.com',
                'password' => Hash::make('Vendor@123'),
                'role' => 'vendor',
                'status' => 1
            ]);

            $vendor = Vendor::create([
                'user_id' => $vendorUser->id,
                'area_id' => $area->id,
                'name' => 'CV. Bahari Teknik',
                'email' => 'sales@bahariteknik.com',
                'status' => 'active'
            ]);

            // 4. Buat Rapat Pleno
            $meeting = PlenaryMeeting::create([
                'meeting_title' => 'Rapat Penatapan Bantuan Mesin 2026',
                'meeting_date' => date('Y-m-d'),
                'location' => 'Gedung KKP',
                'created_by' => $admin->id
            ]);

            $meetingItem = PlenaryMeetingItem::create([
                'plenary_meeting_id' => $meeting->id,
                'cooperative_id' => $cooperative->id,
                'item_id' => $item->id,
                'package_quantity' => 5,
                'created_by' => $admin->id
            ]);

            // 5. Buat Kontrak Pengadaan
            $procurement = Procurement::create([
                'vendor_id' => $vendor->id,
                'procurement_number' => 'KONTRAK/2026/001',
                'procurement_date' => date('Y-m-d'),
                'annual_budget_id' => $budget->id,
                'status' => 'completed',
                'created_by' => $admin->id
            ]);

            $procItem = ProcurementItem::create([
                'procurement_id' => $procurement->id,
                'plenary_meeting_item_id' => $meetingItem->id,
                'quantity' => 5,
                'unit_price' => 25000000, 
                'total_price' => 125000000, 
                'process_status' => 'completed',
                'created_by' => $admin->id
            ]);

            // 6. CATAT TRANSAKSI REALISASI
            AnnualBudgetTransaction::create([
                'annual_budget_id' => $budget->id,
                'item_type_budget_id' => $itemTypeBudget->id,
                'procurement_item_id' => $procItem->id,
                'amount' => 125000000,
            ]);
        });
    }
}
