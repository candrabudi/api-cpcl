<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ProcurementItem;
use App\Models\ProcurementItemProcessStatus;
use App\Models\ProcurementItemStatusLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcurementVendorController extends Controller
{
    /**
     * List vendor procurement items with pagination, search, and eager loaded relations.
     */
    public function index(Request $request)
    {
        $vendorId = $request->user()->vendor->id;

        $query = ProcurementItem::with([
            'procurement',
            'plenaryMeetingItem.item',       // item details
            'plenaryMeetingItem.cooperative', // cooperative info
            'deliveryLogs',
            'processLogs',
        ])->where('vendor_id', $vendorId);

        // Apply search by procurement number, item name, delivery/process status, or item ID
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('procurement', function ($qp) use ($search) {
                    $qp->where('procurement_number', 'like', "%$search%");
                })
                ->orWhereHas('plenaryMeetingItem.item', function ($qi) use ($search) {
                    $qi->where('name', 'like', "%$search%")
                       ->orWhere('id', $search);
                })
                ->orWhere('delivery_status', 'like', "%$search%")
                ->orWhere('process_status', 'like', "%$search%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $items = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::success('Vendor procurement items retrieved', $items);
    }

    /**
     * Update delivery status.
     */
    public function updateDeliveryStatus(Request $request, $id)
    {
        $vendorId = $request->user()->vendor->id;
        $item = ProcurementItem::find($id);

        if (!$item) {
            return ApiResponse::error('Procurement item not found', 404);
        }

        if ($item->vendor_id != $vendorId) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'delivery_status' => 'required|string|max:255',
            'area_id' => 'nullable|exists:areas,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        DB::beginTransaction();
        try {
            $oldStatus = $item->delivery_status;
            $item->delivery_status = $request->delivery_status;
            $item->save();

            ProcurementItemStatusLog::create([
                'procurement_item_id' => $item->id,
                'old_delivery_status' => $oldStatus,
                'new_delivery_status' => $request->delivery_status,
                'area_id' => $request->area_id ?? null,
                'status_date' => Carbon::now()->format('Y-m-d'),
                'changed_by' => $vendorId,
                'notes' => $request->notes ?? null,
            ]);

            DB::commit();

            return ApiResponse::success('Delivery status updated', $item->load([
                'plenaryMeetingItem.item',
                'plenaryMeetingItem.cooperative',
                'deliveryLogs',
                'processLogs',
            ]));
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update delivery status: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update process status.
     */
    public function updateProcessStatus(Request $request, $id)
    {
        $vendorId = $request->user()->vendor->id;
        $item = ProcurementItem::find($id);

        if (!$item) {
            return ApiResponse::error('Procurement item not found', 404);
        }

        if ($item->vendor_id != $vendorId) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'process_status' => 'required|in:pending,purchase,production,completed',
            'production_start_date' => 'nullable|date',
            'production_end_date' => 'nullable|date|after_or_equal:production_start_date',
            'area_id' => 'nullable|exists:areas,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        DB::beginTransaction();
        try {
            $oldStatus = $item->process_status;
            $item->process_status = $request->process_status;
            $item->save();

            ProcurementItemProcessStatus::create([
                'procurement_item_id' => $item->id,
                'status' => $request->process_status,
                'production_start_date' => $request->production_start_date ?? null,
                'production_end_date' => $request->production_end_date ?? null,
                'area_id' => $request->area_id ?? null,
                'changed_by' => $vendorId,
                'notes' => $request->notes ?? null,
                'status_date' => Carbon::now()->format('Y-m-d'),
            ]);

            DB::commit();

            return ApiResponse::success('Process status updated', $item->load([
                'plenaryMeetingItem.item',
                'plenaryMeetingItem.cooperative',
                'deliveryLogs',
                'processLogs',
            ]));
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update process status: '.$e->getMessage(), 500);
        }
    }
}
