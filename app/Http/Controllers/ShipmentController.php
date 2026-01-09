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
    /**
     * Check user access to shipment data
     * Prevents unauthorized access and data leaks
     */
    private function checkAccess($user, $vendorId = null): ?object
    {
        // Admin & Superadmin have full access
        if (in_array($user->role, ['admin', 'superadmin'])) {
            return null;
        }
        
        // Vendor can only access their own shipments
        if ($user->role === 'vendor') {
            // SECURITY: Ensure vendor exists
            if (!$user->vendor) {
                return ApiResponse::error('Vendor profile not found', 403);
            }

            // SECURITY: Vendor can only access their own data
            if ($vendorId && $user->vendor->id != $vendorId) {
                return ApiResponse::error('Unauthorized access to vendor data', 403);
            }
            return null;
        }

        // Default: deny access
        return ApiResponse::error('Unauthorized', 403);
    }

    /**
     * List all shipments with filtering
     * SECURITY: Vendors can only see their own shipments
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Shipment::with([
            'vendor', 
            'items.procurementItem.plenaryMeetingItem.item', 
            'statusLogs.user'
        ])->orderByDesc('id');

        // Archive Filter
        if ($request->get('filter') === 'archived') {
            $query->onlyTrashed();
        } elseif ($request->get('show_archived') === 'true') {
            $query->withTrashed();
        }

        // SECURITY FIX: Vendor dapat HANYA melihat shipment mereka sendiri
        if ($user->role === 'vendor') {
            if (!$user->vendor) {
                return ApiResponse::error('Vendor profile not found', 403);
            }
            // Force filter to vendor's own shipments - IGNORE any vendor_id parameter
            $query->where('vendor_id', $user->vendor->id);
        } elseif ($request->filled('vendor_id')) {
            // Only admin/superadmin can filter by vendor_id
            $query->where('vendor_id', $request->vendor_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $allowedStatuses = ['pending', 'prepared', 'shipped', 'delivered', 'received', 'returned', 'cancelled'];
            if (in_array($request->status, $allowedStatuses)) {
                $query->where('status', $request->status);
            }
        }

        // Search by tracking number
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('tracking_number', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->get('per_page', 15), 100); // Max 100 items
        
        return ApiResponse::success('Shipments retrieved', $query->paginate($perPage));
    }

    /**
     * Show shipment detail
     * SECURITY: Access control enforced
     */
    public function show(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid shipment ID', 400);
        }

        $shipment = Shipment::withTrashed()->with([
            'vendor',
            'items.procurementItem.plenaryMeetingItem.item',
            'statusLogs.user'
        ])->find($id);

        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        // SECURITY: Check access permission
        $accessCheck = $this->checkAccess($request->user(), $shipment->vendor_id);
        if ($accessCheck) {
            return $accessCheck;
        }

        return ApiResponse::success('Shipment detail', $shipment);
    }

    /**
     * Create new shipment
     * TRANSACTION: Protected with atomic operations
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        // Determine vendor_id
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

        // Validation
        $validator = Validator::make($request->all(), [
            'vendor_id' => ($user->role === 'vendor') ? 'nullable' : 'required|exists:vendors,id',
            'tracking_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.procurement_item_id' => 'required|exists:procurement_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // TRANSACTION: Atomic create shipment + items + status log
        try {
            DB::beginTransaction();

            // Create shipment
            $shipment = Shipment::create([
                'vendor_id' => $vendorId,
                'tracking_number' => $request->tracking_number,
                'status' => 'pending',
                'notes' => $request->notes,
                'created_by' => $user->id,
            ]);

            // Create shipment items
            foreach ($request->items as $item) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'procurement_item_id' => $item['procurement_item_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            // Create initial status log
            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => 'pending',
                'notes' => 'Shipment created',
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

    /**
     * Update shipment status
     * TRANSACTION: Protected with atomic operations
     * SECURITY: Status progression validation
     */
    public function updateStatus(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid shipment ID', 400);
        }

        $shipment = Shipment::find($id);
        if (!$shipment) {
            return ApiResponse::error('Shipment not found', 404);
        }

        // SECURITY: Check access permission
        $accessCheck = $this->checkAccess($request->user(), $shipment->vendor_id);
        if ($accessCheck) {
            return $accessCheck;
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,prepared,shipped,delivered,received,returned,cancelled',
            'notes' => 'nullable|string|max:1000',
            'tracking_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // Status progression validation
        $statusOrder = [
            'pending' => 1,
            'prepared' => 2,
            'shipped' => 3,
            'delivered' => 4,
            'received' => 5,
        ];

        $currentLevel = $statusOrder[$shipment->status] ?? 0;
        $newLevel = $statusOrder[$request->status] ?? 0;

        // BUSINESS RULE: Cannot go backwards in status
        if (isset($statusOrder[$request->status]) && isset($statusOrder[$shipment->status])) {
            if ($newLevel < $currentLevel) {
                return ApiResponse::error('Cannot revert shipment status', 400);
            }
            // Allow re-updating "shipped" status for tracking updates
            if ($newLevel == $currentLevel && $request->status !== 'shipped') {
                return ApiResponse::error('Only shipped status can be updated multiple times (e.g. for tracking)', 400);
            }
        }

        // TRANSACTION: Atomic update shipment + create status log
        try {
            DB::beginTransaction();

            $oldStatus = $shipment->status;

            // Update shipment
            $shipment->status = $request->status;
            
            if ($request->filled('tracking_number')) {
                $shipment->tracking_number = $request->tracking_number;
            }

            // Auto-set timestamps based on status
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

            // Create status log
            ShipmentStatusLog::create([
                'shipment_id' => $shipment->id,
                'status' => $request->status,
                'notes' => $request->notes,
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

    /**
     * Delete (archive) shipment
     * TRANSACTION: Protected soft delete
     * SECURITY: Admin only
     */
    public function destroy(Request $request, $id)
    {
        // SECURITY: Admin/superadmin only
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

        // TRANSACTION: Protected soft delete
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
