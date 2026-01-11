<?php

namespace App\Console\Commands;

use App\Models\InspectionReport;
use App\Models\InspectionReportItem;
use App\Models\Shipment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateInspectionReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inspection:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically generate draft Inspection Reports (BA Pemeriksaan) for delivered shipments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for delivered shipments without inspection reports...');

        // Find shipments that are delivered but don't have an inspection report yet
        $deliveredShipments = Shipment::where('status', 'delivered')
            ->whereDoesntHave('inspectionReports')
            ->with(['items.procurementItem'])
            ->get();

        if ($deliveredShipments->isEmpty()) {
            $this->info('No new delivered shipments found.');
            return;
        }

        foreach ($deliveredShipments as $shipment) {
            try {
                DB::beginTransaction();

                // Generate BA Number: BA/SPEC/[YYYYMMDD]/[SHIPMENT_ID]
                $reportNumber = 'BA/INSP/' . Carbon::now()->format('Ymd') . '/' . str_pad($shipment->id, 5, '0', STR_PAD_LEFT);

                // Create the Inspection Report Header
                $report = InspectionReport::create([
                    'procurement_id' => $shipment->items->first()?->procurementItem?->procurement_id,
                    'shipment_id' => $shipment->id,
                    'report_number' => $reportNumber,
                    'inspection_date' => Carbon::now()->format('Y-m-d'),
                    'status' => 'draft',
                    'notes' => 'Generated automatically for shipment #' . $shipment->tracking_number,
                ]);

                // Create checklist items from shipment items
                foreach ($shipment->items as $sItem) {
                    InspectionReportItem::create([
                        'inspection_report_id' => $report->id,
                        'procurement_item_id' => $sItem->procurement_item_id,
                        'shipment_item_id' => $sItem->id,
                        'expected_quantity' => $sItem->quantity,
                        'actual_quantity' => 0, // To be filled by inspector
                        'is_matched' => false,
                        'condition' => 'pending',
                    ]);
                }

                DB::commit();
                $this->info("Generated report: {$reportNumber}");
                Log::info("Inspection report {$reportNumber} generated for shipment #{$shipment->id}");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to generate report for shipment #{$shipment->id}: " . $e->getMessage());
                Log::error("Failed to generate inspection report: " . $e->getMessage());
            }
        }

        $this->info('Done.');
    }
}
