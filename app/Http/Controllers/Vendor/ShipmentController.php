<?php

namespace App\Http\Controllers\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ShipmentController extends Controller
{
    /**
     * Get vendor from authenticated user
     */
    private function getVendor($user)
    {
        if (!$user || !$user->vendor) {
            return null;
        }
        return $user->vendor;
    }

    /**
     * List all shipments for vendor
     */
    public function index(Request $request)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Vendor profile not found', 403);
        }

        $query = Shipment::with([
            'items.procurementItem.plenaryMeetingItem.item', 
            'statusLogs.user'
        ])
        ->where('vendor_id', $vendor->id)
        ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('tracking_number', 'like', "%{$request->search}%");
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        
        return ApiResponse::success('Vendor shipments retrieved', $query->paginate($perPage));
    }

    /**
     * Show shipment detail
     */
    public function show(Request $request, $id)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Vendor profile not found', 403);
        }

        $shipment = Shipment::with([
            'items.procurementItem.plenaryMeetingItem.item',
            'statusLogs.user'
        ])
        ->where('vendor_id', $vendor->id)
        ->find($id);

        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        return ApiResponse::success('Shipment detail', $shipment);
    }

    /**
     * Create new shipment
     */
    public function store(Request $request)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Vendor profile not found', 403);
        }

        $validator = Validator::make($request->all(), [
            'tracking_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.procurement_item_id' => 'required|exists:procurement_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $shipment = Shipment::create([
                'vendor_id' => $vendor->id,
                'tracking_number' => $request->tracking_number,
                'status' => 'pending',
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->items as $item) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'procurement_item_id' => $item['procurement_item_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => 'pending',
                'notes' => 'Shipment created via Mobile',
                'created_by' => Auth::id(),
                'changed_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::success('Shipment created', $shipment->load('items'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create shipment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update shipment status
     */
    public function updateStatus(Request $request, $id)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Vendor profile not found', 403);
        }

        $shipment = Shipment::where('vendor_id', $vendor->id)->find($id);
        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,prepared,shipped,delivered,received,returned,cancelled',
            'notes' => 'nullable|string|max:1000',
            'tracking_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $shipment->status = $request->status;
            if ($request->filled('tracking_number')) {
                $shipment->tracking_number = $request->tracking_number;
            }

            if ($request->status === 'shipped' && !$shipment->shipped_at) {
                $shipment->shipped_at = now();
            }
            
            $shipment->save();

            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => $request->status,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'changed_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::success('Shipment status updated', $shipment);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update shipment status: ' . $e->getMessage(), 500);
        }
    }
}
