<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ItemType;
use App\Models\ItemTypeBudget;
use App\Models\ProcurementItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ItemTypeController extends Controller
{
    /**
     * Check if user is admin/superadmin
     * SECURITY: Only admin can manage item types (except read operations)
     */
    private function checkAdmin($user): ?object
    {
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            \Log::warning('Unauthorized item type access attempt', [
                'user_id' => $user->id,
                'role' => $user->role,
            ]);
            return ApiResponse::error('Unauthorized: Admin access required', 403);
        }
        return null;
    }

    /**
     * List all item types
     * SECURITY: Public read access (for dropdowns, etc)
     */
    public function index(Request $request)
    {
        try {
            $perPage = min((int) $request->get('per_page', 15), 100); // Max 100
            $query = ItemType::query()->orderBy('name');

            // Archive Filter
            if ($request->get('filter') === 'archived') {
                $query->onlyTrashed();
            } elseif ($request->get('show_archived') === 'true') {
                $query->withTrashed();
            }

            // Search filter
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where('name', 'like', "%{$search}%");
            }

            $types = $query->paginate($perPage);

            return ApiResponse::success('Item types retrieved', $types);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve item types', [
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to retrieve item types: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Show item type detail
     * SECURITY: Public read access
     */
    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item type ID', 400);
        }

        $itemType = ItemType::withTrashed()->find($id);

        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        return ApiResponse::success('Item type detail', $itemType);
    }

    /**
     * Create new item type
     * TRANSACTION: Protected create operation
     * SECURITY: Admin only
     */
    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_types,name',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // TRANSACTION: Create item type
        try {
            DB::beginTransaction();

            $itemType = ItemType::create([
                'name' => $request->name,
            ]);

            DB::commit();

            \Log::info('Item type created', [
                'item_type_id' => $itemType->id,
                'name' => $itemType->name,
                'created_by' => Auth::id(),
            ]);

            return ApiResponse::success('Item type created', $itemType, 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to create item type', [
                'name' => $request->name,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to create item type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update item type
     * TRANSACTION: Protected update operation
     * SECURITY: Admin only
     */
    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item type ID', 400);
        }

        $itemType = ItemType::find($id);

        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_types,name,' . $id,
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // TRANSACTION: Update item type
        try {
            DB::beginTransaction();

            $oldName = $itemType->name;
            $itemType->update([
                'name' => $request->name,
            ]);

            DB::commit();

            \Log::info('Item type updated', [
                'item_type_id' => $itemType->id,
                'old_name' => $oldName,
                'new_name' => $itemType->name,
                'updated_by' => Auth::id(),
            ]);

            return ApiResponse::success('Item type updated', $itemType);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to update item type', [
                'item_type_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to update item type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete item type (soft delete)
     * TRANSACTION: Protected delete operation
     * SECURITY: Admin only
     */
    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item type ID', 400);
        }

        $itemType = ItemType::find($id);

        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        // TRANSACTION: Delete item type
        try {
            DB::beginTransaction();

            $itemTypeName = $itemType->name;
            $itemType->delete();

            DB::commit();

            \Log::info('Item type deleted (archived)', [
                'item_type_id' => $id,
                'name' => $itemTypeName,
                'deleted_by' => $request->user()->id,
            ]);

            return ApiResponse::success('Item type deleted (archived)');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to delete item type', [
                'item_type_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete item type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get budgets for item type
     * SECURITY: Public read access
     */
    public function getBudgets($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item type ID', 400);
        }

        $itemType = ItemType::with('budgets')->find($id);

        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        return ApiResponse::success('Item type budgets retrieved', $itemType->budgets);
    }

    /**
     * Create budget for item type
     * TRANSACTION: Protected create operation
     * SECURITY: Admin only
     */
    public function storeBudget(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item type ID', 400);
        }

        $itemType = ItemType::find($id);
        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:1900|max:2100',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // Check if budget for this year already exists
        if ($itemType->budgets()->where('year', $request->year)->exists()) {
            return ApiResponse::error('Budget for this year already exists', 409);
        }

        // TRANSACTION: Create budget
        try {
            DB::beginTransaction();

            $budget = $itemType->budgets()->create([
                'year' => $request->year,
                'amount' => $request->amount,
                'used_amount' => 0,
            ]);

            DB::commit();

            \Log::info('Item type budget created', [
                'item_type_id' => $id,
                'budget_id' => $budget->id,
                'year' => $budget->year,
                'amount' => $budget->amount,
                'created_by' => Auth::id(),
            ]);

            return ApiResponse::success('Budget created', $budget, 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to create budget', [
                'item_type_id' => $id,
                'year' => $request->year,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to create budget: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update budget for item type
     * TRANSACTION: Protected update operation
     * SECURITY: Admin only
     * BUSINESS RULE: Cannot update if transactions exist
     */
    public function updateBudget(Request $request, $id, $budgetId)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id) || !is_numeric($budgetId)) {
            return ApiResponse::error('Invalid ID', 400);
        }

        $itemType = ItemType::find($id);
        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        $budget = $itemType->budgets()->find($budgetId);
        if (!$budget) {
            return ApiResponse::error('Budget not found', 404);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // BUSINESS RULE: Check if amount is changing
        if ($request->amount != $budget->amount) {
            // Check if there are procurement items using this item type in this year
            $hasTransactions = ProcurementItem::whereHas('item', function ($q) use ($id) {
                $q->where('item_type_id', $id);
            })->whereYear('created_at', $budget->year)->exists();

            if ($hasTransactions) {
                \Log::warning('Attempted to update budget with existing transactions', [
                    'item_type_id' => $id,
                    'budget_id' => $budgetId,
                    'year' => $budget->year,
                    'user_id' => Auth::id(),
                ]);

                return ApiResponse::error(
                    'Cannot update budget amount because transactions exist for this item type in ' . $budget->year,
                    400
                );
            }
        }

        // TRANSACTION: Update budget
        try {
            DB::beginTransaction();

            $oldAmount = $budget->amount;
            $budget->update([
                'amount' => $request->amount,
            ]);

            DB::commit();

            \Log::info('Item type budget updated', [
                'item_type_id' => $id,
                'budget_id' => $budgetId,
                'year' => $budget->year,
                'old_amount' => $oldAmount,
                'new_amount' => $budget->amount,
                'updated_by' => Auth::id(),
            ]);

            return ApiResponse::success('Budget updated', $budget);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to update budget', [
                'item_type_id' => $id,
                'budget_id' => $budgetId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to update budget: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete budget for item type
     * TRANSACTION: Protected delete operation
     * SECURITY: Admin only
     * BUSINESS RULE: Cannot delete if transactions exist
     */
    public function destroyBudget(Request $request, $id, $budgetId)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id) || !is_numeric($budgetId)) {
            return ApiResponse::error('Invalid ID', 400);
        }

        $itemType = ItemType::find($id);
        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        $budget = $itemType->budgets()->find($budgetId);
        if (!$budget) {
            return ApiResponse::error('Budget not found', 404);
        }

        // BUSINESS RULE: Check if there are transactions
        $hasTransactions = ProcurementItem::whereHas('item', function ($q) use ($id) {
            $q->where('item_type_id', $id);
        })->whereYear('created_at', $budget->year)->exists();

        if ($hasTransactions) {
            \Log::warning('Attempted to delete budget with existing transactions', [
                'item_type_id' => $id,
                'budget_id' => $budgetId,
                'year' => $budget->year,
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::error(
                'Cannot delete budget because transactions exist for this item type in ' . $budget->year,
                400
            );
        }

        // TRANSACTION: Delete budget
        try {
            DB::beginTransaction();

            $budgetYear = $budget->year;
            $budgetAmount = $budget->amount;
            $budget->delete();

            DB::commit();

            \Log::info('Item type budget deleted', [
                'item_type_id' => $id,
                'budget_id' => $budgetId,
                'year' => $budgetYear,
                'amount' => $budgetAmount,
                'deleted_by' => $request->user()->id,
            ]);

            return ApiResponse::success('Budget deleted');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to delete budget', [
                'item_type_id' => $id,
                'budget_id' => $budgetId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to delete budget: ' . $e->getMessage(), 500);
        }
    }
}
