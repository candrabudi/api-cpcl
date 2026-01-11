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
use Carbon\Carbon;

class ShipmentController extends Controller
{
    private function getVendor($user)
    {
        if (!$user || !$user->vendor) {
            return null;
        }
        return $user->vendor;
    }

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

    public function show(Request $request, $id)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Vendor profile not found', 403);
        }

        $shipment = Shipment::with([
            'items.procurementItem.plenaryMeetingItem.item'
        ])
        ->where('vendor_id', $vendor->id)
        ->find($id);

        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        return ApiResponse::success('Shipment detail', $shipment);
    }

    public function trackingHistory(Request $request, $id)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Vendor profile not found', 403);
        }

        $shipment = Shipment::where('vendor_id', $vendor->id)->find($id);
        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        $logs = ShipmentStatusLog::with(['user', 'area'])
            ->where('shipment_id', $id)
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success('Shipment tracking history retrieved', $logs);
    }

    public function listUnshippedItems(Request $request)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Vendor profile not found', 403);
        }

        // Get unshipped procurement items for this vendor
        $query = \App\Models\ProcurementItem::whereHas('procurement', function($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })
            ->where('delivery_status', '!=', 'shipped')
            ->with([
                'procurement',
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

        // Group by Cooperative Area ID
        $grouped = $readyItems->groupBy(function($item) {
            return $item->plenaryMeetingItem->cooperative->area_id ?? 0; // 0 for no area
        })->map(function($items, $areaId) {
            $firstItem = $items->first();
            $area = $firstItem->plenaryMeetingItem->cooperative->area ?? null;
            
            // Build the base AREA object
            $entry = $area ? [
                'id' => $area->id,
                'name' => $area->name,
                'code' => $area->code ?? null,
                'regency' => $area->regency_name ?? null, 
                // Adapt fields based on Area model structure (assuming standard fields)
            ] : [
                'id' => 0,
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
                    'cooperative_name' => $coop->name ?? 'Unknown Cooperative', // Info tambahan krn grouped by area
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
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Vendor profile not found', 403);
        }

        $validator = Validator::make($request->all(), [
            'tracking_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'area_id' => 'required|exists:areas,id',
            'items' => 'required|array|min:1',
            'items.*.procurement_item_id' => 'required|exists:procurement_items,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $shipment = Shipment::create([
                'vendor_id' => $vendor->id,
                'area_id' => $request->area_id,
                'tracking_number' => $request->tracking_number,
                'status' => 'pending',
                'notes' => $request->notes,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->items as $itemData) {
                $procurementItem = \App\Models\ProcurementItem::with('plenaryMeetingItem.cooperative')
                    ->whereHas('procurement', function($q) use ($vendor) {
                        $q->where('vendor_id', $vendor->id);
                    })->find($itemData['procurement_item_id']);

                if (!$procurementItem) {
                    throw new \Exception("Procurement item ID {$itemData['procurement_item_id']} not found or does not belong to your vendor.");
                }

                // Verify AREA match
                $itemAreaId = $procurementItem->plenaryMeetingItem->cooperative->area_id ?? null;
                if ($itemAreaId != $request->area_id) {
                     throw new \Exception("Item '{$procurementItem->plenaryMeetingItem->item->name}' belongs to an area (ID: $itemAreaId) different from the selected shipment area (ID: {$request->area_id}).");
                }

                // Validation: Production status
                $itemModel = $procurementItem->plenaryMeetingItem?->item;
                if ($itemModel && $itemModel->process_type === 'production') {
                    if ($procurementItem->process_status !== 'completed') {
                        throw new \Exception("Item '{$itemModel->name}' cannot be shipped. Production process is not yet completed.");
                    }
                }

                // Quantity Logic
                $shippedQty = \App\Models\ShipmentItem::where('procurement_item_id', $procurementItem->id)
                    ->whereHas('shipment', function($q) {
                        $q->where('status', '!=', 'cancelled');
                    })
                    ->sum('quantity');
                $remainingQty = $procurementItem->quantity - $shippedQty;

                $quantityToShip = $remainingQty;
                if (isset($itemData['quantity']) && is_numeric($itemData['quantity'])) {
                    $quantityToShip = (int) $itemData['quantity'];
                }

                if ($quantityToShip <= 0) {
                     throw new \Exception("Invalid quantity to ship for item '{$procurementItem->id}'. Must be greater than 0.");
                }

                if ($quantityToShip > $remainingQty) {
                    $itemName = $procurementItem->plenaryMeetingItem->item->name ?? 'Unknown Item';
                    throw new \Exception("Quantity for '$itemName' ($quantityToShip) exceeds remaining quantity ($remainingQty).");
                }

                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'procurement_item_id' => $procurementItem->id,
                    'quantity' => $quantityToShip,
                ]);

                // Update procurement item delivery status
                $newTotalShipped = $shippedQty + $quantityToShip;
                $newDeliveryStatus = ($newTotalShipped >= $procurementItem->quantity) ? 'shipped' : 'partially_shipped';
                $procurementItem->update(['delivery_status' => $newDeliveryStatus]);
            }

            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => 'pending',
                'notes' => 'Shipment created via Mobile',
                'latitude' => $request->latitude ?? null, // Nullable safe
                'longitude' => $request->longitude ?? null,
                'area_id' => $request->area_id, // Use shipment area as default log area
                'created_by' => Auth::id(),
                'changed_at' => Carbon::now(),
            ]);

            DB::commit();

            return ApiResponse::success('Shipment created', $shipment->load('items', 'area'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create shipment: ' . $e->getMessage(), 500);
        }
    }

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
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'area_id' => 'nullable|exists:areas,id',
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
                $shipment->shipped_at = Carbon::now();
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

            // Trigger BA generation check for procurement if shipment delivered
            if ($request->status === 'delivered') {
                $procurementIds = \App\Models\ShipmentItem::where('shipment_id', $shipment->id)
                    ->join('procurement_items', 'shipment_items.procurement_item_id', '=', 'procurement_items.id')
                    ->distinct()
                    ->pluck('procurement_items.procurement_id');

                $inspectionCtrl = new \App\Http\Controllers\InspectionReportController();
                foreach ($procurementIds as $pId) {
                    $inspectionCtrl->generateForProcurement($pId);
                }
            }

            return ApiResponse::success('Shipment status updated', $shipment);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update shipment status: ' . $e->getMessage(), 500);
        }
    }

    public function track(Request $request, $id)
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
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'area_id' => 'nullable|exists:areas,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $log = \App\Models\ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => $shipment->status,
                'notes' => $request->notes ?? 'Location update',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'area_id' => $request->area_id,
                'created_by' => Auth::id(),
                'changed_at' => Carbon::now(),
            ]);

            return ApiResponse::success('Shipment location tracked', $log);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to track shipment: ' . $e->getMessage(), 500);
        }
    }
}
