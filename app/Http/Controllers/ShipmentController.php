<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentStatusLog;
use Carbon\Carbon;
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
            'items.procurementItem.item.type', 
            'items.procurementItem.plenaryMeetingItem.item.type', 
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
            'items.procurementItem.item.type',
            'items.procurementItem.plenaryMeetingItem.item.type'
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

    public function listUnshippedItems(Request $request)
    {
        $adminCheck = in_array($request->user()->role, ['admin', 'superadmin']);
        $vendorId = $request->get('vendor_id');

        // If user is vendor role (accessing this controller for some reason), force vendorId
        if ($request->user()->role === 'vendor' && $request->user()->vendor) {
             $vendorId = $request->user()->vendor->id;
        }

        $query = \App\Models\ProcurementItem::query();

        if ($vendorId) {
             $query->whereHas('procurement', function($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            });
        }

        $query->where('delivery_status', '!=', 'shipped')
             ->with([
                'procurement.vendor',
                'plenaryMeetingItem.cooperative.area', // Load Area
                'plenaryMeetingItem.item.type',
            ])
            ->withSum(['shipmentItems' => function($q) {
                $q->whereHas('shipment', function($sq) {
                    $sq->where('status', '!=', 'cancelled');
                });
            }], 'quantity');
            
        $items = $query->get();

        // Filter items that are ready for shipping
        $readyItems = $items->filter(function($procItem) {
             $itemModel = $procItem->plenaryMeetingItem?->item;
             if ($itemModel && $itemModel->process_type === 'production') {
                 return $procItem->process_status === 'completed';
             }
             return true; 
        });

        // Group by Cooperative AREA ID
        $grouped = $readyItems->groupBy(function($item) {
            return $item->plenaryMeetingItem->cooperative->area_id ?? 0;
        })->map(function($items, $areaId) {
            $firstItem = $items->first();
            $area = $firstItem->plenaryMeetingItem->cooperative->area ?? null;
            
            // Build the base AREA object
            $entry = $area ? [
                'id' => $area->id,
                'name' => $area->name,
                'code' => $area->code ?? null,
                'regency' => $area->regency_name ?? null, 
                // Add more area fields if needed
            ] : [
                'id' => $areaId, // 0 usually
                'name' => "Unknown Area / No Area Assigned",
                'code' => null,
            ];

            // Add items directly to the object
            $entry['items'] = $items->map(function($item) {
                $shippedQty = $item->shipment_items_sum_quantity ?? 0;
                $remainingQty = max(0, $item->quantity - $shippedQty);

                if ($remainingQty <= 0) return null;

                $coop = $item->plenaryMeetingItem->cooperative;

                return [
                    'procurement_item_id' => $item->id,
                    'procurement_id' => $item->procurement_id,
                    'procurement_number' => $item->procurement->procurement_number,
                    'vendor_id' => $item->procurement->vendor_id,
                    'vendor_name' => $item->procurement->vendor->name ?? 'Unknown Vendor',
                    'cooperative_name' => $coop->name ?? 'Unknown Cooperative', // Info tambahan
                    'item_id' => $item->plenaryMeetingItem->item_id ?? null,
                    'item_name' => $item->plenaryMeetingItem->item->name ?? 'Unknown Item',
                    'item_unit' => $item->plenaryMeetingItem->item->unit ?? 'Unit',
                    'quantity_total' => $item->quantity,
                    'quantity_shipped' => $shippedQty,
                    'quantity_remaining' => $remainingQty,
                    'delivery_status' => $item->delivery_status,
                ];
            })->filter()->values();
            
            return $entry;
        })->filter(function($entry) {
            return $entry['items']->isNotEmpty();
        })->values();

        return ApiResponse::success('Unshipped items grouped by area', $grouped);
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
            'cooperative_id' => 'required|exists:cooperatives,id',
            'tracking_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'area_id' => 'nullable|exists:areas,id',
            'items' => 'required|array|min:1',
            'items.*.procurement_item_id' => 'required|exists:procurement_items,id',
            'items.*.quantity' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $shipment = Shipment::create([
                'vendor_id' => $vendorId,
                'cooperative_id' => $request->cooperative_id,
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

                    // Automatically use remaining quantity or requested quantity
                    $totalShipped = \App\Models\ShipmentItem::where('procurement_item_id', $procurementItem->id)
                        ->whereHas('shipment', function($q) {
                            $q->where('status', '!=', 'cancelled');
                        })
                        ->sum('quantity');
                    
                    $remainingQty = $procurementItem->quantity - $totalShipped;
                    $quantityToShip = $itemData['quantity'] ?? $remainingQty;

                    if ($quantityToShip <= 0) {
                        throw new \Exception("Item '{$itemModel->name}' has no remaining quantity to ship.");
                    }

                    if ($quantityToShip > $remainingQty) {
                        throw new \Exception("Requested quantity for '{$itemModel->name}' exceed remaining quantity ({$remainingQty}).");
                    }

                    ShipmentItem::create([
                        'shipment_id' => $shipment->id,
                        'procurement_item_id' => $procurementItem->id,
                        'quantity' => $quantityToShip,
                    ]);

                    // Update delivery status
                    $newTotalShipped = $totalShipped + $quantityToShip;
                    $newDeliveryStatus = ($newTotalShipped >= $procurementItem->quantity) ? 'shipped' : 'partially_shipped';
                    $procurementItem->update(['delivery_status' => $newDeliveryStatus]);
                }
            }

            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => 'pending',
                'notes' => 'Shipment created',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'area_id' => $request->area_id,
                'created_by' => $user->id,
                'changed_at' => Carbon::now(),
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
                $shipment->shipped_at = Carbon::now();
            }
            if ($request->status === 'delivered' && !$shipment->delivered_at) {
                $shipment->delivered_at = Carbon::now();
            }
            if ($request->status === 'received' && !$shipment->received_at) {
                $shipment->received_at = Carbon::now();
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
                'changed_at' => Carbon::now(),
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
