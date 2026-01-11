<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\InspectionReport;
use App\Models\InspectionReportItem;
use App\Models\InspectionReportPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Shipment;

class InspectionReportController extends Controller
{
    /**
     * Automatically generate draft Inspection Reports (BA Pemeriksaan) for delivered shipments.
     * This can be called by a cron job or manually via URL.
     */
    public function generateFromShipments()
    {
        // Find shipments that are delivered but don't have an inspection report yet
        $deliveredShipments = Shipment::where('status', 'delivered')
            ->whereDoesntHave('inspectionReports')
            ->with(['items.procurementItem'])
            ->get();

        if ($deliveredShipments->isEmpty()) {
            return ApiResponse::success('No new delivered shipments found for inspection generation.');
        }

        $generatedCount = 0;
        foreach ($deliveredShipments as $shipment) {
            $report = $this->createReport($shipment);
            if ($report) {
                $generatedCount++;
            }
        }

        return ApiResponse::success("$generatedCount inspection reports generated successfully.");
    }

    /**
     * Generate BA specifically for a procurement once all items are fully delivered.
     */
    public function generateForProcurement($procurementId)
    {
        $procurement = \App\Models\Procurement::with(['items'])->find($procurementId);
        if (!$procurement) return;

        // Check if all items in this procurement have been shipped/delivered
        $allItemsShipped = true;
        foreach ($procurement->items as $pItem) {
            $totalShipped = \App\Models\ShipmentItem::where('procurement_item_id', $pItem->id)
                ->whereHas('shipment', function($q) {
                    $q->where('status', 'delivered');
                })
                ->sum('quantity');
            
            if ($totalShipped < $pItem->quantity) {
                $allItemsShipped = false;
                break;
            }
        }

        if ($allItemsShipped) {
            // Check if BA already exists for this procurement
            $exists = InspectionReport::where('procurement_id', $procurementId)->exists();
            if ($exists) return;

            try {
                DB::beginTransaction();
                $reportNumber = 'BA/INSP/' . Carbon::now()->format('Ymd') . '/P-' . str_pad($procurement->id, 5, '0', STR_PAD_LEFT);
                
                $report = InspectionReport::create([
                    'procurement_id' => $procurement->id,
                    'report_number' => $reportNumber,
                    'inspection_date' => Carbon::now()->format('Y-m-d'),
                    'status' => 'draft',
                    'notes' => 'Generated automatically: All items delivered for procurement #' . $procurement->procurement_number,
                ]);

                foreach ($procurement->items as $pItem) {
                    InspectionReportItem::create([
                        'inspection_report_id' => $report->id,
                        'procurement_item_id' => $pItem->id,
                        'expected_quantity' => $pItem->quantity,
                        'actual_quantity' => 0,
                        'is_matched' => false,
                        'condition' => 'pending',
                    ]);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
            }
        }
    }

    private function createReport(Shipment $shipment)
    {
        try {
            DB::beginTransaction();

            $reportNumber = 'BA/INSP/' . Carbon::now()->format('Ymd') . '/' . str_pad($shipment->id, 5, '0', STR_PAD_LEFT);

            $procurementId = $shipment->items->first()?->procurementItem?->procurement_id;
            if (!$procurementId) {
                return null;
            }

            $report = InspectionReport::create([
                'procurement_id' => $procurementId,
                'shipment_id' => $shipment->id,
                'report_number' => $reportNumber,
                'inspection_date' => Carbon::now()->format('Y-m-d'),
                'status' => 'draft',
                'notes' => 'Generated automatically for shipment #' . $shipment->tracking_number,
            ]);

            foreach ($shipment->items as $sItem) {
                // Validation: Only include items belonging to the same procurement
                if ($sItem->procurementItem?->procurement_id == $procurementId) {
                    InspectionReportItem::create([
                        'inspection_report_id' => $report->id,
                        'procurement_item_id' => $sItem->procurement_item_id,
                        'shipment_item_id' => $sItem->id,
                        'expected_quantity' => $sItem->quantity,
                        'actual_quantity' => 0,
                        'is_matched' => false,
                        'condition' => 'pending',
                    ]);
                }
            }

            DB::commit();
            return $report;
        } catch (\Exception $e) {
            DB::rollBack();
            return null;
        }
    }

    /**
     * List all inspection reports
     */
    public function index(Request $request)
    {
        $query = InspectionReport::with(['procurement', 'shipment']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('procurement_id')) {
            $query->where('procurement_id', $request->procurement_id);
        }

        $perPage = min((int) $request->get('per_page', 10), 100);
        return ApiResponse::success('Inspection reports retrieved', $query->paginate($perPage));
    }

    /**
     * Show detail of an inspection report
     */
    public function show($id)
    {
        $report = InspectionReport::with([
            'items.procurementItem.item.type', 
            'items.procurementItem.plenaryMeetingItem.item.type', 
            'photos', 
            'procurement', 
            'shipment'
        ])->find($id);

        if (!$report) {
            return ApiResponse::error('Inspection report not found', 404);
        }

        return ApiResponse::success('Inspection report detail', $report);
    }

    /**
     * Update/Fill the checklist and metadata of the report
     */
    public function update(Request $request, $id)
    {
        $report = InspectionReport::find($id);
        if (!$report) {
            return ApiResponse::error('Inspection report not found', 404);
        }

        if ($report->status === 'completed' || $report->status === 'verified') {
            return ApiResponse::error('Cannot update a completed or verified report', 400);
        }

        $validator = Validator::make($request->all(), [
            'inspector_name' => 'nullable|string|max:255',
            'inspection_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:inspection_report_items,id',
            'items.*.actual_quantity' => 'required|integer|min:0',
            'items.*.condition' => 'required|string|max:100',
            'items.*.notes' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Update Header
            $report->update([
                'inspector_name' => $request->inspector_name ?? $report->inspector_name,
                'inspection_date' => $request->inspection_date ?? $report->inspection_date,
                'notes' => $request->notes ?? $report->notes,
                'status' => $request->get('complete', false) ? 'completed' : 'draft',
            ]);

            // Auto-update parent procurement to 'completed' if report is finalized
            if ($report->status === 'completed' && $report->procurement) {
                $report->procurement->update(['status' => 'completed']);
                \Log::info("Procurement {$report->procurement->procurement_number} marked as COMPLETED via Inspection Report #{$report->report_number}");
            }

            // Update Checklist Items
            foreach ($request->items as $itemData) {
                $item = InspectionReportItem::where('inspection_report_id', $report->id)
                    ->find($itemData['id']);
                
                if ($item) {
                    $item->update([
                        'actual_quantity' => $itemData['actual_quantity'],
                        'condition' => $itemData['condition'],
                        'notes' => $itemData['notes'] ?? null,
                        'is_matched' => $itemData['actual_quantity'] == $item->expected_quantity,
                    ]);
                }
            }

            // Handle Photo Uploads (Multiple)
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photoFile) {
                    $path = $photoFile->store('inspection_photos', 'public');
                    InspectionReportPhoto::create([
                        'inspection_report_id' => $report->id,
                        'photo_path' => $path,
                    ]);
                }
            }

            DB::commit();

            return ApiResponse::success('Inspection report updated successfully', $report->load('items', 'photos'));
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update inspection report: ' . $e->getMessage(), 500);
        }
    }
}
