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
    /**
     * Get vendor from authenticated user
     * SECURITY: Ensures user has vendor profile
     */
    private function getVendor($user)
    {
        if (!$user || !$user->vendor) {
            return null;
        }
        return $user->vendor;
    }

    /**
     * Get vendor dashboard statistics
     */
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
            })->where('process_status', 'pending')->count(),
            'production' => ProcurementItem::whereHas('procurement', function($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })->where('process_status', 'production')->count(),
            'completed' => ProcurementItem::whereHas('procurement', function($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })->where('process_status', 'completed')->count(),
        ];

        return ApiResponse::success('Vendor dashboard statistics', $stats);
    }

    /**
     * List procurement items for authenticated vendor
     */
    public function index(Request $request)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized: Vendor profile not found', 403);
        }

        $query = ProcurementItem::with([
            'procurement',
            'plenaryMeetingItem.item',
            'plenaryMeetingItem.cooperative',
            'processStatuses.user',
            'shipmentItems.shipment'
        ])->whereHas('procurement', function($q) use ($vendor) {
            $q->where('vendor_id', $vendor->id);
        });

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('procurement', function ($qp) use ($search) {
                    $qp->where('procurement_number', 'like', "%$search%");
                })
                ->orWhereHas('plenaryMeetingItem.item', function ($qi) use ($search) {
                    $qi->where('name', 'like', "%$search%");
                });
            });
        }

        if ($request->filled('process_status')) {
            $allowedStatuses = ['pending', 'purchase', 'production', 'completed'];
            if (in_array($request->process_status, $allowedStatuses)) {
                $query->where('process_status', $request->process_status);
            }
        }

        $perPage = min((int) $request->get('per_page', 10), 100);
        $items = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::success('Vendor procurement items retrieved', $items);
    }

    /**
     * Show procurement item detail
     */
    public function show(Request $request, $id)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized: Vendor profile not found', 403);
        }

        $item = ProcurementItem::with([
            'procurement.vendor',
            'plenaryMeetingItem.item',
            'plenaryMeetingItem.cooperative',
            'processStatuses.user',
            'shipmentItems.shipment'
        ])->whereHas('procurement', function($q) use ($vendor) {
            $q->where('vendor_id', $vendor->id);
        })->find($id);

        if (!$item) {
            return ApiResponse::error('Procurement item not found', 404);
        }

        return ApiResponse::success('Procurement item detail', $item);
    }

    /**
     * Update procurement item process status
     */
    public function updateProcessStatus(Request $request, $id)
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
            'process_status' => 'required|in:pending,purchase,production,completed',
            'production_start_date' => 'nullable|date',
            'production_end_date' => 'nullable|date|after_or_equal:production_start_date',
            'area_id' => 'nullable|exists:areas,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $oldStatus = $item->process_status;
            $item->process_status = $request->process_status;
            $item->save();

            ProcurementItemProcessStatus::create([
                'procurement_item_id' => $item->id,
                'status' => $request->process_status,
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
                'processStatuses'
            ]));
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update process status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update procurement item delivery status
     */
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
}
