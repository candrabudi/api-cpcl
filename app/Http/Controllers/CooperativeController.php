<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Area;
use App\Models\Cooperative;
use App\Models\ProcurementItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CooperativeController extends Controller
{
    /**
     * Check if user is admin/superadmin
     * SECURITY: Only admin can manage cooperatives
     */
    private function checkAdmin($user): ?object
    {
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            \Log::warning('Unauthorized cooperative access attempt', [
                'user_id' => $user?->id ?? 'anonymous',
                'role' => $user?->role ?? 'none',
                'ip' => request()->ip()
            ]);
            return ApiResponse::error('Unauthorized: Admin access required', 403);
        }
        return null;
    }

    /**
     * List all cooperatives
     * SECURITY: Admin only (for now)
     */
    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $perPage = (int) $request->get('per_page', 10);
            $query = Cooperative::query()->orderByDesc('id');

            // Archive Filter
            if ($request->get('filter') === 'archived') {
                $query->onlyTrashed();
            } elseif ($request->get('show_archived') === 'true') {
                $query->withTrashed();
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('kusuka_id_number', 'like', "%{$search}%");
                });
            }

            $data = $query->paginate($perPage);
            return ApiResponse::success('Cooperatives retrieved', $data);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve cooperatives', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return ApiResponse::error('Failed to retrieve cooperatives', 500);
        }
    }

    /**
     * Show cooperative detail
     */
    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid cooperative ID', 400);
        }

        try {
            $cooperative = Cooperative::withTrashed()->with('area')->find($id);

            if (!$cooperative) {
                return ApiResponse::error('Cooperative not found', 404);
            }

            return ApiResponse::success('Cooperative detail', $cooperative);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve cooperative detail', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve cooperative details', 500);
        }
    }

    /**
     * Get procurements for a cooperative
     */
    public function getCooperativeProcurements(Request $request, $cooperativeID)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($cooperativeID)) {
            return ApiResponse::error('Invalid cooperative ID', 400);
        }

        try {
            $cooperative = Cooperative::find($cooperativeID);
            if (!$cooperative) {
                return ApiResponse::error('Cooperative not found', 404);
            }

            $items = ProcurementItem::with('procurement', 'plenaryMeetingItem')
                ->whereHas('plenaryMeetingItem', function ($q) use ($cooperativeID) {
                    $q->where('cooperative_id', $cooperativeID);
                })
                ->get();

            if ($items->isEmpty()) {
                return ApiResponse::success('No procurements found for this cooperative', [
                    'cooperative' => $cooperative,
                    'procurements' => []
                ]);
            }

            $procurements = $items->groupBy('procurement_id')->map(function ($group) {
                $procurement = $group->first()->procurement;
                $procurementTotal = $group->sum('total_price');

                return [
                    'procurement_id' => $procurement->id,
                    'procurement_number' => $procurement->procurement_number,
                    'procurement_date' => $procurement->procurement_date,
                    'total_spent' => $procurementTotal,
                ];
            })->values();

            return ApiResponse::success('Cooperative procurements retrieved', [
                'cooperative' => $cooperative,
                'procurements' => $procurements,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve cooperative procurements', [
                'cooperative_id' => $cooperativeID,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve cooperative procurements', 500);
        }
    }

    /**
     * Get specific items from a procurement for a cooperative
     */
    public function getCooperativeProcurementItems(Request $request, $cooperativeID, $procurementID)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($cooperativeID) || !is_numeric($procurementID)) {
            return ApiResponse::error('Invalid ID format', 400);
        }

        try {
            $items = ProcurementItem::with([
                'procurement',
                'plenaryMeetingItem.item',
                'plenaryMeetingItem.cooperative',
                'statusLogs',
                'processStatuses',
            ])
            ->whereHas('plenaryMeetingItem', function ($q) use ($cooperativeID) {
                $q->where('cooperative_id', $cooperativeID);
            })
            ->where('procurement_id', $procurementID)
            ->get();

            if ($items->isEmpty()) {
                return ApiResponse::error('No items found for this cooperative and procurement', 404);
            }

            $totalSpent = $items->sum('total_price');
            $procurement = $items->first()->procurement;

            $itemsData = $items->map(function ($item) {
                return [
                    'procurement_item_id' => $item->id,
                    'item_id' => $item->plenaryMeetingItem->item->id,
                    'item_name' => $item->plenaryMeetingItem->item->name,
                    'vendor_id' => $item->vendor_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'delivery_status' => $item->delivery_status,
                    'process_status' => $item->process_status,
                    'status_logs' => $item->statusLogs,
                    'process_statuses' => $item->processStatuses,
                ];
            });

            return ApiResponse::success('Procurement items retrieved', [
                'procurement' => [
                    'id' => $procurement->id,
                    'procurement_number' => $procurement->procurement_number,
                    'procurement_date' => $procurement->procurement_date,
                    'total_spent' => $totalSpent,
                ],
                'items' => $itemsData,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve cooperative procurement items', [
                'cooperative_id' => $cooperativeID,
                'procurement_id' => $procurementID,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve items', 500);
        }
    }

    /**
     * Create new cooperative
     * TRANSACTION: Protected multi-table operation (potentially Area integration)
     * SECURITY: Admin only
     */
    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|max:255|unique:cooperatives,registration_number',
            'kusuka_id_number' => 'nullable|string|max:255|unique:cooperatives,kusuka_id_number',
            'established_year' => 'nullable|integer|digits:4|min:1900|max:' . date('Y'),
            'street_address' => 'nullable|string|max:255',
            'area_id' => 'required|integer|exists:areas,id',
            'phone_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'chairman_name' => 'nullable|string|max:255',
            'secretary_name' => 'nullable|string|max:255',
            'treasurer_name' => 'nullable|string|max:255',
            'chairman_phone_number' => 'nullable|string|max:30',
            'member_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $area = Area::findOrFail($request->area_id);

            $cooperative = Cooperative::create([
                'name' => trim($request->name),
                'registration_number' => $request->registration_number ? trim($request->registration_number) : null,
                'kusuka_id_number' => $request->kusuka_id_number ? trim($request->kusuka_id_number) : null,
                'established_year' => $request->established_year,
                'street_address' => $request->street_address,
                'area_id' => $area->id,
                'village' => $area->sub_district_name,
                'district' => $area->district_name,
                'regency' => $area->city_name,
                'province' => $area->province_name,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'chairman_name' => $request->chairman_name,
                'secretary_name' => $request->secretary_name,
                'treasurer_name' => $request->treasurer_name,
                'chairman_phone_number' => $request->chairman_phone_number,
                'member_count' => $request->member_count ?? 0,
            ]);

            DB::commit();

            \Log::info('Cooperative created successfully', [
                'cooperative_id' => $cooperative->id,
                'name' => $cooperative->name,
                'created_by' => Auth::id()
            ]);

            return ApiResponse::success('Cooperative created', ['id' => $cooperative->id], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to create cooperative', [
                'name' => $request->name,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to create cooperative: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update cooperative
     * TRANSACTION: Protected update operation
     * SECURITY: Admin only
     */
    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid cooperative ID', 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'registration_number' => 'nullable|string|max:255|unique:cooperatives,registration_number,' . $id,
            'kusuka_id_number' => 'nullable|string|max:255|unique:cooperatives,kusuka_id_number,' . $id,
            'established_year' => 'nullable|integer|digits:4|min:1900|max:' . date('Y'),
            'street_address' => 'nullable|string|max:255',
            'area_id' => 'sometimes|required|integer|exists:areas,id',
            'phone_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'chairman_name' => 'nullable|string|max:255',
            'secretary_name' => 'nullable|string|max:255',
            'treasurer_name' => 'nullable|string|max:255',
            'chairman_phone_number' => 'nullable|string|max:30',
            'member_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $cooperative = Cooperative::lockForUpdate()->find($id);
            if (!$cooperative) {
                throw new \Exception('Cooperative not found.');
            }

            // Prepare update data
            $updateData = [];

            // Handle standard fields
            $fields = [
                'name', 'registration_number', 'kusuka_id_number', 'established_year',
                'street_address', 'phone_number', 'email', 'chairman_name',
                'secretary_name', 'treasurer_name', 'chairman_phone_number', 'member_count'
            ];

            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $field === 'name' ? trim($request->$field) : $request->$field;
                }
            }

            // Handle Area ID update
            if ($request->has('area_id')) {
                $area = Area::find($request->area_id); // Validated by exists rule
                $updateData['area_id'] = $area->id;
                $updateData['village'] = $area->sub_district_name;
                $updateData['district'] = $area->district_name;
                $updateData['regency'] = $area->city_name;
                $updateData['province'] = $area->province_name;
            }

            $oldName = $cooperative->name;
            $cooperative->update($updateData);

            DB::commit();

            \Log::info('Cooperative updated successfully', [
                'cooperative_id' => $id,
                'old_name' => $oldName,
                'new_name' => $cooperative->name,
                'updated_by' => Auth::id()
            ]);

            return ApiResponse::success('Cooperative updated');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to update cooperative', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to update cooperative: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete cooperative (soft delete)
     * TRANSACTION: Protected delete operation
     * SECURITY: Admin only
     */
    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid cooperative ID', 400);
        }

        try {
            DB::beginTransaction();

            $cooperative = Cooperative::find($id);
            if (!$cooperative) {
                throw new \Exception('Cooperative not found.');
            }

            $name = $cooperative->name;
            $cooperative->delete();

            DB::commit();

            \Log::info('Cooperative deleted (soft delete)', [
                'cooperative_id' => $id,
                'name' => $name,
                'deleted_by' => Auth::id()
            ]);

            return ApiResponse::success('Cooperative deleted');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to delete cooperative', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to delete cooperative: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Restore a deleted cooperative
     * TRANSACTION: Protected restore operation
     * SECURITY: Admin only
     */
    public function restore(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid cooperative ID', 400);
        }

        try {
            DB::beginTransaction();

            $cooperative = Cooperative::onlyTrashed()->find($id);
            if (!$cooperative) {
                throw new \Exception('Archived cooperative not found.');
            }

            $cooperative->restore();

            DB::commit();

            \Log::info('Cooperative restored', [
                'cooperative_id' => $id,
                'name' => $cooperative->name,
                'restored_by' => Auth::id()
            ]);

            return ApiResponse::success('Cooperative restored successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to restore cooperative', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to restore cooperative: ' . $e->getMessage(), 500);
        }
    }
}
