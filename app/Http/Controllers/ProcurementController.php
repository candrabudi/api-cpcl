<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AnnualBudget;
use App\Models\AnnualBudgetTransaction;
use App\Models\PlenaryMeetingItem;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\ProcurementItemProcessStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcurementController extends Controller
{
    /**
     * Check if user is admin/superadmin
     * SECURITY: Only admin can manage procurements
     */
    private function checkAdmin($user): ?object
    {
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            \Log::warning('Unauthorized procurement access attempt', [
                'user_id' => $user->id,
                'role' => $user->role,
            ]);
            return ApiResponse::error('Unauthorized: Admin access required', 403);
        }
        return null;
    }

    /**
     * Recalculate annual budget totals
     * TRANSACTION: Must be called within existing transaction
     */
    private function recalcBudget(Procurement $procurement): void
    {
        $budget = AnnualBudget::find($procurement->annual_budget_id ?? null);
        if (!$budget) {
            \Log::warning('Budget not found for procurement', [
                'procurement_id' => $procurement->id,
                'annual_budget_id' => $procurement->annual_budget_id,
            ]);
            return;
        }

        // Calculate total used from all procurement items in this budget
        $totalUsed = ProcurementItem::whereHas('procurement', function ($q) use ($budget) {
            $q->where('annual_budget_id', $budget->id);
        })->sum('total_price');

        $budget->used_budget = $totalUsed;
        $budget->remaining_budget = $budget->total_budget - $totalUsed;
        $budget->save();

        \Log::debug('Budget recalculated', [
            'budget_id' => $budget->id,
            'total_used' => $totalUsed,
            'remaining' => $budget->remaining_budget,
        ]);
    }

    /**
     * List all procurements
     * SECURITY: Admin only
     */
    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $perPage = min((int) $request->get('per_page', 15), 100); // Max 100
        
        $query = Procurement::with(['vendor', 'items.plenaryMeetingItem.item', 'creator'])
            ->orderByDesc('id');

        // Archive Filter
        if ($request->get('filter') === 'archived') {
            $query->onlyTrashed();
        } elseif ($request->get('show_archived') === 'true') {
            $query->withTrashed();
        }

        // Search filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('procurement_number', 'like', "%{$search}%");
        }

        // Status filter
        if ($request->filled('status')) {
            $allowedStatuses = ['draft', 'processed', 'completed', 'cancelled'];
            if (in_array($request->status, $allowedStatuses)) {
                $query->where('status', $request->status);
            }
        }

        // Vendor filter
        if ($request->filled('vendor_id') && is_numeric($request->vendor_id)) {
            $query->where('vendor_id', $request->vendor_id);
        }

        return ApiResponse::success('Procurements retrieved', $query->paginate($perPage));
    }

    /**
     * Show procurement detail
     * SECURITY: Admin only
     */
    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid procurement ID', 400);
        }

        $procurement = Procurement::withTrashed()->with([
            'vendor',
            'items' => function ($query) {
                $query->with([
                    'processStatuses.user',
                    'plenaryMeetingItem.item',
                    'plenaryMeetingItem.cooperative',
                    'plenaryMeeting'
                ]);
            },
            'creator',
            'logs.user',
            'annualBudget'
        ])->find($id);

        if (!$procurement) {
            return ApiResponse::error('Procurement not found', 404);
        }

        // Recalculate budget in transaction
        try {
            DB::beginTransaction();
            $this->recalcBudget($procurement);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to recalculate budget', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
            ]);
            // Continue with existing budget values
        }

        $totalPaid = $procurement->items->sum('total_price');

        return ApiResponse::success('Procurement detail', [
            'procurement' => $procurement,
            'total_paid' => $totalPaid,
            'remaining_budget' => $procurement->annualBudget?->remaining_budget ?? null,
        ]);
    }

    /**
     * Create new procurement
     * TRANSACTION: Protected multi-table operation
     * SECURITY: Admin only
     */
    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        // Validation
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:vendors,id',
            'procurement_number' => 'required|string|max:100|unique:procurements,procurement_number',
            'procurement_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.plenary_meeting_item_id' => 'required|exists:plenary_meeting_items,id',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // TRANSACTION: Create procurement + items + budget transactions + status logs
        try {
            DB::beginTransaction();

            // Get or create annual budget for current year
            $annualBudget = AnnualBudget::firstOrCreate(
                ['budget_year' => Carbon::now()->year],
                ['total_budget' => 0, 'used_budget' => 0, 'remaining_budget' => 0]
            );

            // Create procurement
            $procurement = Procurement::create([
                'vendor_id' => $request->vendor_id,
                'procurement_number' => $request->procurement_number,
                'procurement_date' => Carbon::parse($request->procurement_date),
                'notes' => $request->notes,
                'status' => 'draft',
                'annual_budget_id' => $annualBudget->id,
                'created_by' => Auth::id(),
            ]);

            // Create procurement items
            foreach ($request->items as $item) {
                $meetingItem = PlenaryMeetingItem::findOrFail($item['plenary_meeting_item_id']);

                // Validate package quantity
                if (!$meetingItem->package_quantity || $meetingItem->package_quantity <= 0) {
                    throw new \Exception("Package quantity not defined for plenary meeting item ID: {$meetingItem->id}");
                }

                // Check if already in another procurement
                $existingProcItem = ProcurementItem::where('plenary_meeting_item_id', $meetingItem->id)->first();
                if ($existingProcItem) {
                    throw new \Exception("Plenary meeting item ID {$meetingItem->id} is already in procurement ID {$existingProcItem->procurement_id}");
                }

                $quantity = $meetingItem->package_quantity;
                $unitPrice = $item['unit_price'];
                $totalPrice = $quantity * $unitPrice;

                // Create procurement item
                $procItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $meetingItem->id,
                    'plenary_meeting_id' => $meetingItem->plenary_meeting_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'delivery_status' => 'pending',
                    'process_status' => 'pending',
                    'created_by' => Auth::id(),
                ]);

                // Create budget transaction
                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'procurement_item_id' => $procItem->id,
                    'amount' => $totalPrice,
                ]);

                // Create initial process status
                ProcurementItemProcessStatus::create([
                    'procurement_item_id' => $procItem->id,
                    'status' => 'pending',
                    'changed_by' => Auth::id(),
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);
            }

            // Recalculate budget
            $this->recalcBudget($procurement);

            DB::commit();

            \Log::info('Procurement created successfully', [
                'procurement_id' => $procurement->id,
                'procurement_number' => $procurement->procurement_number,
                'vendor_id' => $request->vendor_id,
                'items_count' => count($request->items),
                'created_by' => Auth::id(),
            ]);

            return ApiResponse::success('Procurement created', $procurement->load('items'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to create procurement', [
                'vendor_id' => $request->vendor_id,
                'procurement_number' => $request->procurement_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to create procurement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update procurement
     * TRANSACTION: Protected multi-table operation
     * SECURITY: Admin only
     */
    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid procurement ID', 400);
        }

        $procurement = Procurement::find($id);
        if (!$procurement) {
            return ApiResponse::error('Procurement not found', 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|exists:vendors,id',
            'procurement_number' => 'required|string|max:100|unique:procurements,procurement_number,' . $id,
            'procurement_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|required|in:draft,processed,completed,cancelled',
            'items' => 'required|array|min:1',
            'items.*.plenary_meeting_item_id' => 'required|exists:plenary_meeting_items,id',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // TRANSACTION: Update procurement + delete old items + create new items + budget transactions
        try {
            DB::beginTransaction();

            $annualBudget = AnnualBudget::where('budget_year', Carbon::now()->year)->firstOrFail();

            // Update procurement
            $procurement->update([
                'vendor_id' => $request->vendor_id,
                'procurement_number' => $request->procurement_number,
                'procurement_date' => Carbon::parse($request->procurement_date),
                'notes' => $request->notes,
                'status' => $request->status ?? $procurement->status,
                'annual_budget_id' => $annualBudget->id,
            ]);

            // Delete old items and related data (soft delete handles cascade)
            // Also delete related budget transactions
            $oldItemIds = $procurement->items()->pluck('id')->toArray();
            if (!empty($oldItemIds)) {
                AnnualBudgetTransaction::whereIn('procurement_item_id', $oldItemIds)->delete();
                ProcurementItemProcessStatus::whereIn('procurement_item_id', $oldItemIds)->delete();
            }
            $procurement->items()->delete();

            // Create new items
            foreach ($request->items as $item) {
                $meetingItem = PlenaryMeetingItem::findOrFail($item['plenary_meeting_item_id']);
                
                $quantity = $meetingItem->package_quantity ?? 0;
                if ($quantity <= 0) {
                    throw new \Exception("Invalid package quantity for plenary meeting item ID: {$meetingItem->id}");
                }

                $unitPrice = $item['unit_price'];
                $totalPrice = $quantity * $unitPrice;

                $procItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $meetingItem->id,
                    'plenary_meeting_id' => $meetingItem->plenary_meeting_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'delivery_status' => 'pending',
                    'process_status' => 'pending',
                    'created_by' => Auth::id(),
                ]);

                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'procurement_item_id' => $procItem->id,
                    'amount' => $totalPrice,
                ]);

                ProcurementItemProcessStatus::create([
                    'procurement_item_id' => $procItem->id,
                    'status' => 'pending',
                    'changed_by' => Auth::id(),
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);
            }

            // Recalculate budget
            $this->recalcBudget($procurement);

            DB::commit();

            \Log::info('Procurement updated successfully', [
                'procurement_id' => $procurement->id,
                'procurement_number' => $procurement->procurement_number,
                'items_count' => count($request->items),
                'updated_by' => Auth::id(),
            ]);

            return ApiResponse::success('Procurement updated', $procurement->load('items'));
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to update procurement', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to update procurement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete procurement (soft delete)
     * TRANSACTION: Protected delete operation
     * SECURITY: Admin only
     */
    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid procurement ID', 400);
        }

        $procurement = Procurement::find($id);
        if (!$procurement) {
            return ApiResponse::error('Procurement not found', 404);
        }

        // TRANSACTION: Delete procurement + recalculate budget
        try {
            DB::beginTransaction();

            $procurement->delete();
            
            // Recalculate budget after deletion
            $this->recalcBudget($procurement);

            DB::commit();

            \Log::info('Procurement deleted (archived)', [
                'procurement_id' => $id,
                'procurement_number' => $procurement->procurement_number,
                'deleted_by' => $request->user()->id,
            ]);

            return ApiResponse::success('Procurement deleted (archived)');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to delete procurement', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete procurement: ' . $e->getMessage(), 500);
        }
    }
}
