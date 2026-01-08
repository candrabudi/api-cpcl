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
    private function checkAccess($user, $vendorId = null)
    {
        if (in_array($user->role, ['admin', 'superadmin'])) {
            return null;
        }
        
        if ($user->role === 'vendor') {
            if ($vendorId && $user->vendor->id != $vendorId) {
                return ApiResponse::error('Unauthorized', 403);
            }
            return null;
        }

        return ApiResponse::error('Unauthorized', 403);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Shipment::with(['vendor', 'items.procurementItem.plenaryMeetingItem.item', 'statusLogs.user'])
            ->orderByDesc('id');

        // Archive Filter
        if ($request->get('filter') === 'archived') {
            $query->onlyTrashed();
        } elseif ($request->get('show_archived') === 'true') {
            $query->withTrashed();
        }

        if ($user->role === 'vendor') {
            $query->where('vendor_id', $user->vendor->id);
        } elseif ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('tracking_number', 'like', "%{$request->search}%");
        }

        $perPage = (int) $request->get('per_page', 15);
        return ApiResponse::success('Shipments retrieved', $query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $shipment = Shipment::withTrashed()->with([
            'vendor',
            'items.procurementItem.plenaryMeetingItem.item',
            'statusLogs.user'
        ])->find($id);

        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        $accessCheck = $this->checkAccess($request->user(), $shipment->vendor_id);
        if ($accessCheck) return $accessCheck;

        return ApiResponse::success('Shipment detail', $shipment);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $vendorId = ($user->role === 'vendor') ? $user->vendor->id : $request->vendor_id;

        if (!$vendorId) {
            return ApiResponse::error('Vendor ID is required.', 422);
        }

        $validator = Validator::make($request->all(), [
            'vendor_id' => ($user->role === 'vendor') ? 'nullable' : 'required|exists:vendors,id',
            'tracking_number' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.procurement_item_id' => 'required|exists:procurement_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        DB::beginTransaction();
        try {
            $shipment = Shipment::create([
                'vendor_id' => $vendorId,
                'tracking_number' => $request->tracking_number,
                'status' => 'pending',
                'notes' => $request->notes,
                'created_by' => $user->id,
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
                'notes' => 'Shipment created',
                'created_by' => $user->id,
                'changed_at' => now(),
            ]);

            DB::commit();
            return ApiResponse::success('Shipment created', $shipment->load('items'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create shipment: ' . $e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $shipment = Shipment::find($id);
        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        $accessCheck = $this->checkAccess($request->user(), $shipment->vendor_id);
        if ($accessCheck) return $accessCheck;

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,prepared,shipped,delivered,received,returned,cancelled',
            'notes' => 'nullable|string',
            'tracking_number' => 'nullable|string',
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

        // Restriction: Cannot go back, can stay on 'shipped' for updates
        if (isset($statusOrder[$request->status]) && isset($statusOrder[$shipment->status])) {
            if ($newLevel < $currentLevel) {
                return ApiResponse::error('Cannot revert shipment status.', 400);
            }
            if ($newLevel == $currentLevel && $request->status !== 'shipped') {
                return ApiResponse::error('Only shipped status can be updated multiple times (e.g. for tracking).', 400);
            }
        }

        DB::beginTransaction();
        try {
            $shipment->status = $request->status;
            if ($request->filled('tracking_number')) {
                $shipment->tracking_number = $request->tracking_number;
            }

            if ($request->status === 'shipped' && !$shipment->shipped_at) $shipment->shipped_at = now();
            if ($request->status === 'delivered' && !$shipment->delivered_at) $shipment->delivered_at = now();
            if ($request->status === 'received' && !$shipment->received_at) $shipment->received_at = now();
            
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

    public function destroy(Request $request, $id)
    {
        $adminCheck = in_array($request->user()->role, ['admin', 'superadmin']);
        if (!$adminCheck) return ApiResponse::error('Unauthorized', 403);

        $shipment = Shipment::find($id);
        if (!$shipment) return ApiResponse::error('Shipment not found', 404);

        try {
            $shipment->delete();
            return ApiResponse::success('Shipment deleted (archived)');
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete shipment', 500);
        }
    }
}
