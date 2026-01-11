<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ShipmentController extends Controller
{
    private function checkAccess($user, $vendorId = null): ?object
    {
        if (in_array($user->role, ['admin', 'superadmin'])) {
            return null;
        }
        
        if ($user->role === 'vendor') {
            if (!$user->vendor) {
                return ApiResponse::error('Vendor profile not found', 403);
            }

            if ($vendorId && $user->vendor->id != $vendorId) {
                return ApiResponse::error('Unauthorized access to vendor data', 403);
            }
            return null;
        }

        return ApiResponse::error('Unauthorized', 403);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Shipment::with([
            'vendor', 
            'items.procurementItem.plenaryMeetingItem.item', 
            'statusLogs.user'
        ])->orderByDesc('id');

        if ($request->get('filter') === 'archived') {
            $query->onlyTrashed();
        } elseif ($request->get('show_archived') === 'true') {
            $query->withTrashed();
        }

        if ($user->role === 'vendor') {
            if (!$user->vendor) {
                return ApiResponse::error('Vendor profile not found', 403);
            }
            $query->where('vendor_id', $user->vendor->id);
        } elseif ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('status')) {
            $allowedStatuses = ['pending', 'prepared', 'shipped', 'delivered', 'received', 'returned', 'cancelled'];
            if (in_array($request->status, $allowedStatuses)) {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('tracking_number', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        
        return ApiResponse::success('Shipments retrieved', $query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid shipment ID', 400);
        }

        $shipment = Shipment::withTrashed()->with([
            'vendor',
            'items.procurementItem.plenaryMeetingItem.item'
        ])->find($id);

        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        $accessCheck = $this->checkAccess($request->user(), $shipment->vendor_id);
        if ($accessCheck) {
            return $accessCheck;
        }

        return ApiResponse::success('Shipment detail', $shipment);
    }

    public function trackingHistory(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid shipment ID', 400);
        }

        $shipment = Shipment::withTrashed()->find($id);

        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        $accessCheck = $this->checkAccess($request->user(), $shipment->vendor_id);
        if ($accessCheck) {
            return $accessCheck;
        }

        $logs = ShipmentStatusLog::with(['user', 'area'])
            ->where('shipment_id', $id)
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success('Shipment tracking history retrieved', $logs);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'vendor') {
            if (!$user->vendor) {
                return ApiResponse::error('Vendor profile not found', 403);
            }
            $vendorId = $user->vendor->id;
        } else {
            $vendorId = $request->vendor_id;
        }

        if (!$vendorId) {
            return ApiResponse::error('Vendor ID is required', 422);
        }

        $validator = Validator::make($request->all(), [
            'vendor_id' => ($user->role === 'vendor') ? 'nullable' : 'required|exists:vendors,id',
            'tracking_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'area_id' => 'nullable|exists:areas,id',
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
                'vendor_id' => $vendorId,
                'tracking_number' => $request->tracking_number,
                'status' => 'pending',
                'notes' => $request->notes,
                'created_by' => $user->id,
            ]);

            foreach ($request->items as $itemData) {
                // Validation: Check production process status
                $procurementItem = \App\Models\ProcurementItem::with('plenaryMeetingItem.item')->find($itemData['procurement_item_id']);
                if ($procurementItem) {
                    $itemModel = $procurementItem->plenaryMeetingItem?->item;
                    if ($itemModel && $itemModel->process_type === 'production') {
                        if ($procurementItem->process_status !== 'completed') {
                            throw new \Exception("Item '{$itemModel->name}' cannot be shipped. Production process is not yet completed (current status: {$procurementItem->process_status}).");
                        }
                    }
                }

                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'procurement_item_id' => $itemData['procurement_item_id'],
                    'quantity' => $itemData['quantity'],
                ]);
            }

            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => 'pending',
                'notes' => 'Shipment created',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'area_id' => $request->area_id,
                'created_by' => $user->id,
                'changed_at' => now(),
            ]);

            DB::commit();

            \Log::info('Shipment created successfully', [
                'shipment_id' => $shipment->id,
                'vendor_id' => $vendorId,
                'created_by' => $user->id,
            ]);

            return ApiResponse::success('Shipment created', $shipment->load('items'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to create shipment', [
                'vendor_id' => $vendorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to create shipment: ' . $e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid shipment ID', 400);
        }

        $shipment = Shipment::find($id);
        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        $accessCheck = $this->checkAccess($request->user(), $shipment->vendor_id);
        if ($accessCheck) {
            return $accessCheck;
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,prepared,shipped,delivered,received,returned,cancelled',
            'notes' => 'nullable|string|max:1000',
            'tracking_number' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'area_id' => 'nullable|exists:areas,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        $statusOrder = [
            'pending' => 1,
            'prepared' => 2,
            'shipped' => 3,
            'delivered' => 4,
            'received' => 5,
        ];

        $currentLevel = $statusOrder[$shipment->status] ?? 0;
        $newLevel = $statusOrder[$request->status] ?? 0;

        if (isset($statusOrder[$request->status]) && isset($statusOrder[$shipment->status])) {
            if ($newLevel < $currentLevel) {
                return ApiResponse::error('Cannot revert shipment status', 400);
            }
            if ($newLevel == $currentLevel && $request->status !== 'shipped') {
                return ApiResponse::error('Only shipped status can be updated multiple times (e.g. for tracking)', 400);
            }
        }

        try {
            DB::beginTransaction();

            $oldStatus = $shipment->status;

            $shipment->status = $request->status;
            
            if ($request->filled('tracking_number')) {
                $shipment->tracking_number = $request->tracking_number;
            }

            if ($request->status === 'shipped' && !$shipment->shipped_at) {
                $shipment->shipped_at = now();
            }
            if ($request->status === 'delivered' && !$shipment->delivered_at) {
                $shipment->delivered_at = now();
            }
            if ($request->status === 'received' && !$shipment->received_at) {
                $shipment->received_at = now();
            }
            
            $shipment->save();

            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => $request->status,
                'notes' => $request->notes,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'area_id' => $request->area_id,
                'created_by' => Auth::id(),
                'changed_at' => now(),
            ]);

            DB::commit();

            \Log::info('Shipment status updated', [
                'shipment_id' => $shipment->id,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'updated_by' => Auth::id(),
            ]);

            return ApiResponse::success('Shipment status updated', $shipment);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to update shipment status', [
                'shipment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to update shipment status: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $adminCheck = in_array($request->user()->role, ['admin', 'superadmin']);
        if (!$adminCheck) {
            return ApiResponse::error('Unauthorized: Admin access required', 403);
        }

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid shipment ID', 400);
        }

        $shipment = Shipment::find($id);
        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        try {
            DB::beginTransaction();

            $shipment->delete();

            DB::commit();

            \Log::info('Shipment deleted (archived)', [
                'shipment_id' => $id,
                'deleted_by' => $request->user()->id,
            ]);

            return ApiResponse::success('Shipment deleted (archived)');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to delete shipment', [
                'shipment_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete shipment: ' . $e->getMessage(), 500);
        }
    }
}
