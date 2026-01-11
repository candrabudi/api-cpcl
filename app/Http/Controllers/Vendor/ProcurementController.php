<?php

namespace App\Http\Controllers\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProcurementItem;
use App\Models\ProcurementItemProcessStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ProcurementController extends Controller
{
    private function getVendor($user)
    {
        if (!$user || !$user->vendor) {
            return null;
        }
        return $user->vendor;
    }

    public function dashboard(Request $request)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized: Vendor profile not found', 403);
        }

        $stats = [
            'total_procurements' => ProcurementItem::whereHas('procurement', function($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })->count(),
            'pending_process' => ProcurementItem::whereHas('procurement', function($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })->whereHas('plenaryMeetingItem.item', function($q) {
                $q->where('process_type', 'production');
            })->where('process_status', 'pending')->count(),
            'production' => ProcurementItem::whereHas('procurement', function($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })->whereHas('plenaryMeetingItem.item', function($q) {
                $q->where('process_type', 'production');
            })->where('process_status', 'production')->count(),
            'completed' => ProcurementItem::whereHas('procurement', function($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })->whereHas('plenaryMeetingItem.item', function($q) {
                $q->where('process_type', 'production');
            })->where('process_status', 'completed')->count(),
            'ready_to_ship' => ProcurementItem::whereHas('procurement', function($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })->whereHas('plenaryMeetingItem.item', function($q) {
                $q->where('process_type', 'purchase');
            })->count(),
        ];

        return ApiResponse::success('Vendor dashboard statistics', $stats);
    }

    public function index(Request $request)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized: Vendor profile not found', 403);
        }

        $query = \App\Models\Procurement::withCount('items')
            ->where('vendor_id', $vendor->id);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('procurement_number', 'like', "%$search%");
        }

        if ($request->filled('status')) {
            $allowedStatuses = ['draft', 'processed', 'completed', 'cancelled'];
            if (in_array($request->status, $allowedStatuses)) {
                $query->where('status', $request->status);
            }
        }

        $perPage = min((int) $request->get('per_page', 10), 100);
        $procurements = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::success('Vendor procurements retrieved', $procurements);
    }

    public function show(Request $request, $id)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized: Vendor profile not found', 403);
        }

        $procurement = \App\Models\Procurement::with([
            'items.plenaryMeetingItem.item',
            'items.plenaryMeetingItem.cooperative',
            'items.processStatuses.user',
            'items.processStatuses.productionAttribute',
            'items.shipmentItems.shipment',
            'vendor'
        ])->where('vendor_id', $vendor->id)->find($id);

        if (!$procurement) {
            return ApiResponse::error('Procurement not found', 404);
        }

        return ApiResponse::success('Procurement detail', $procurement);
    }

    public function updateProcessStatus(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid procurement item ID', 400);
        }

        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized: Vendor profile not found', 403);
        }

        $item = ProcurementItem::with(['procurement', 'plenaryMeetingItem.item'])->find($id);
        if (!$item) {
            return ApiResponse::error('Procurement item not found', 404);
        }

        if (!$item->procurement || $item->procurement->vendor_id != $vendor->id) {
            return ApiResponse::error('Unauthorized: This procurement item does not belong to your vendor', 403);
        }

        $processType = $item->plenaryMeetingItem->item->process_type ?? 'production'; // default to production for safety
        if ($processType === 'purchase') {
            return ApiResponse::error('Items with "purchase" process type do not need to go through the production process.', 400);
        }

        $validator = Validator::make($request->all(), [
            'process_status' => 'required|in:pending,purchase,production,completed',
            'production_attribute_id' => 'nullable|exists:production_attributes,id',
            'percentage' => 'nullable|integer|min:0|max:100',
            'production_start_date' => 'nullable|date',
            'production_end_date' => 'nullable|date|after_or_equal:production_start_date',
            'area_id' => 'nullable|exists:areas,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // Logic check: Percentage cannot decrease
        if ($request->filled('percentage')) {
            $latestStatus = \App\Models\ProcurementItemProcessStatus::where('procurement_item_id', $item->id)
                ->orderBy('id', 'desc')
                ->first();
            
            if ($latestStatus && (int)$request->percentage < (int)$latestStatus->percentage) {
                return ApiResponse::error('Percentage cannot be lower than the previous record (' . $latestStatus->percentage . '%)', 400);
            }
        }

        try {
            DB::beginTransaction();

            $oldStatus = $item->process_status;
            $item->process_status = $request->process_status;
            $item->save();

            ProcurementItemProcessStatus::create([
                'procurement_item_id' => $item->id,
                'production_attribute_id' => $request->production_attribute_id,
                'status' => $request->process_status,
                'percentage' => $request->percentage ?? 0,
                'production_start_date' => $request->production_start_date ?? null,
                'production_end_date' => $request->production_end_date ?? null,
                'area_id' => $request->area_id ?? null,
                'changed_by' => Auth::id(),
                'notes' => $request->notes ?? null,
                'status_date' => Carbon::now()->format('Y-m-d'),
            ]);

            DB::commit();

            return ApiResponse::success('Process status updated', $item->load([
                'plenaryMeetingItem.item',
                'processStatuses.productionAttribute'
            ]));
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update process status: ' . $e->getMessage(), 500);
        }
    }

    public function updateDeliveryStatus(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid procurement item ID', 400);
        }

        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized: Vendor profile not found', 403);
        }

        $item = ProcurementItem::with('procurement')->find($id);
        if (!$item) {
            return ApiResponse::error('Procurement item not found', 404);
        }

        if (!$item->procurement || $item->procurement->vendor_id != $vendor->id) {
            return ApiResponse::error('Unauthorized: This procurement item does not belong to your vendor', 403);
        }

        $validator = Validator::make($request->all(), [
            'delivery_status' => 'required|in:pending,prepared,shipped,delivered',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $item->delivery_status = $request->delivery_status;
            $item->save();

            DB::commit();

            return ApiResponse::success('Delivery status updated', $item);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update delivery status: ' . $e->getMessage(), 500);
        }
    }

    public function readyToShip(Request $request)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized: Vendor profile not found', 403);
        }

        $items = ProcurementItem::with([
            'procurement',
            'plenaryMeetingItem.item',
            'plenaryMeetingItem.cooperative'
        ])
        ->whereHas('procurement', function($q) use ($vendor) {
            $q->where('vendor_id', $vendor->id);
        })
        ->where('process_status', 'completed')
        ->where('delivery_status', 'pending')
        ->orderByDesc('id')
        ->get()
        ->map(function ($item) {
            $shippedQty = \App\Models\ShipmentItem::where('procurement_item_id', $item->id)->sum('quantity');
            $remainingQty = max(0, $item->quantity - $shippedQty);

            return [
                'procurement_item_id' => $item->id,
                'procurement_id' => $item->procurement_id,
                'procurement_number' => $item->procurement?->procurement_number,
                'item_id' => $item->plenaryMeetingItem?->item_id,
                'item_name' => $item->plenaryMeetingItem?->item?->name,
                'total_quantity' => $item->quantity,
                'shipped_quantity' => $shippedQty,
                'remaining_quantity' => $remainingQty,
                'unit' => $item->plenaryMeetingItem?->item?->unit,
                'cooperative_id' => $item->plenaryMeetingItem?->cooperative_id,
                'cooperative_name' => $item->plenaryMeetingItem?->cooperative?->name,
                'process_type' => $item->plenaryMeetingItem?->item?->process_type,
                'process_status' => $item->process_status,
                'delivery_status' => $item->delivery_status,
            ];
        })
        ->filter(function ($item) {
            return $item['remaining_quantity'] > 0;
        })
        ->values();

        return ApiResponse::success('Ready to ship items retrieved', $items);
    }
}
