<?php

namespace Database\Seeders;

use App\Models\AnnualBudget;
use App\Models\Cooperative;
use App\Models\Item;
use App\Models\PlenaryMeeting;
use App\Models\PlenaryMeetingItem;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentStatusLog;
use App\Models\Vendor;
use App\Http\Controllers\InspectionReportController;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InspectionFlowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Ensure Vendor exists
            $user = \App\Models\User::first();
            $area = \App\Models\Area::first();
            $vendor = Vendor::firstOrCreate(
                ['name' => 'PT. Logistik Maju Jaya'],
                [
                    'user_id' => $user->id,
                    'area_id' => $area->id,
                    'email' => 'contact@maju-jaya.com',
                    'phone' => '08123456789',
                    'status' => 'active'
                ]
            );

            // 2. Ensure Cooperative exists
            $cooperative = Cooperative::firstOrCreate(
                ['name' => 'Koperasi Bahari Sejahtera'],
                ['email' => 'support@bahari.com']
            );

            // 3. Create Plenary Meeting
            $meeting = PlenaryMeeting::create([
                'meeting_title' => 'Rapat Pleno Pengadaan Mesin 2026',
                'meeting_date' => Carbon::parse('2026-02-15'),
                'notes' => 'Seeded for inspection flow',
                'created_by' => $user->id,
            ]);

            // 4. Create Items
            $item1 = Item::where('name', 'GPS Garmin')->first() ?? Item::create([
                'name' => 'GPS Garmin',
                'unit' => 'Unit',
                'process_type' => 'purchase'
            ]);

            $item2 = Item::where('name', 'Life Jacket')->first() ?? Item::create([
                'name' => 'Life Jacket',
                'unit' => 'Pcs',
                'process_type' => 'purchase'
            ]);

            // 5. Add Items to Plenary Meeting
            $pmItem1 = PlenaryMeetingItem::create([
                'plenary_meeting_id' => $meeting->id,
                'cooperative_id' => $cooperative->id,
                'item_id' => $item1->id,
                'package_quantity' => 10,
                'note' => 'Approved in meeting',
                'created_by' => $user->id,
            ]);

            $pmItem2 = PlenaryMeetingItem::create([
                'plenary_meeting_id' => $meeting->id,
                'cooperative_id' => $cooperative->id,
                'item_id' => $item2->id,
                'package_quantity' => 50,
                'note' => 'Approved in meeting',
                'created_by' => $user->id,
            ]);

            // 6. Create Procurement (Contract)
            $procurement = Procurement::create([
                'vendor_id' => $vendor->id,
                'procurement_number' => 'PROC/INSP/' . time(),
                'procurement_date' => Carbon::parse('2026-03-01'),
                'status' => 'processed',
                'notes' => 'Kontrak Pengadaan Perangkat Kapal',
                'created_by' => $user->id,
            ]);

            // 7. Create Procurement Items
            $pItem1 = ProcurementItem::create([
                'procurement_id' => $procurement->id,
                'plenary_meeting_item_id' => $pmItem1->id,
                'plenary_meeting_id' => $meeting->id,
                'quantity' => 10,
                'unit_price' => 5000000,
                'total_price' => 50000000,
                'process_status' => 'completed',
                'delivery_status' => 'pending'
            ]);

            $pItem2 = ProcurementItem::create([
                'procurement_id' => $procurement->id,
                'plenary_meeting_item_id' => $pmItem2->id,
                'plenary_meeting_id' => $meeting->id,
                'quantity' => 50,
                'unit_price' => 200000,
                'total_price' => 10000000,
                'process_status' => 'completed',
                'delivery_status' => 'pending'
            ]);

            // 8. Create Shipment (Pengiriman)
            $shipment = Shipment::create([
                'vendor_id' => $vendor->id,
                'tracking_number' => 'TRK-INSP-' . time(),
                'status' => 'delivered',
                'shipped_at' => Carbon::now()->subDays(2),
                'delivered_at' => Carbon::now()->subDay(),
                'notes' => 'Pengiriman lengkap batch 1',
                'created_by' => $user->id,
            ]);

            // 9. Add Shipment Items (Full Quantity)
            ShipmentItem::create([
                'shipment_id' => $shipment->id,
                'procurement_item_id' => $pItem1->id,
                'quantity' => 10,
            ]);

            ShipmentItem::create([
                'shipment_id' => $shipment->id,
                'procurement_item_id' => $pItem2->id,
                'quantity' => 50,
            ]);

            // 10. Update Procurement Items to shipped
            $pItem1->update(['delivery_status' => 'shipped']);
            $pItem2->update(['delivery_status' => 'shipped']);

            // 11. Add Logistics Log
            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => 'delivered',
                'notes' => 'Barang telah sampai di lokasi Koperasi',
                'changed_at' => Carbon::now()->subDay(),
            ]);

            echo "Full Flow Seeded: Plenary -> Procurement -> Shipment (Delivered).\n";
            echo "Invoking BA Pemeriksaan generation...\n";

            // 12. Simulate the Cron Job / Controller Logic to generate the BA
            $controller = new InspectionReportController();
            $response = $controller->generateFromShipments();
            
            echo "Result: " . $response->getData()->message . "\n";
        });
    }
}
