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

    public function index(Request $request)
    {
        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $query = ItemType::query()->orderBy('name');

            if ($request->get('filter') === 'archived') {
                $query->onlyTrashed();
            } elseif ($request->get('show_archived') === 'true') {
                $query->withTrashed();
            }

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

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_types,name',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $itemType = ItemType::create([
                'name' => $request->name,
            ]);

            DB::commit();

            return ApiResponse::success('Item type created', $itemType, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create item type: ' . $e->getMessage(), 500);
        }
    }

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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_types,name,' . $id,
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $itemType->update([
                'name' => $request->name,
            ]);

            DB::commit();

            return ApiResponse::success('Item type updated', $itemType);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update item type: ' . $e->getMessage(), 500);
        }
    }

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

        try {
            DB::beginTransaction();
            $itemType->delete();
            DB::commit();

            return ApiResponse::success('Item type deleted (archived)');
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to delete item type: ' . $e->getMessage(), 500);
        }
    }

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

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:1900|max:2100',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        if ($itemType->budgets()->where('year', $request->year)->exists()) {
            return ApiResponse::error('Budget for this year already exists', 409);
        }

        try {
            DB::beginTransaction();

            $budget = $itemType->budgets()->create([
                'year' => $request->year,
                'amount' => $request->amount,
                'used_amount' => 0,
            ]);

            DB::commit();

            return ApiResponse::success('Budget created', $budget, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create budget: ' . $e->getMessage(), 500);
        }
    }

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

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        if ($request->amount != $budget->amount) {
            $hasTransactions = ProcurementItem::whereHas('item', function ($q) use ($id) {
                $q->where('item_type_id', $id);
            })->whereYear('created_at', $budget->year)->exists();

            if ($hasTransactions) {
                return ApiResponse::error(
                    'Cannot update budget amount because transactions exist for this item type in ' . $budget->year,
                    400
                );
            }
        }

        try {
            DB::beginTransaction();
            $budget->update([
                'amount' => $request->amount,
            ]);
            DB::commit();

            return ApiResponse::success('Budget updated', $budget);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update budget: ' . $e->getMessage(), 500);
        }
    }

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

        $hasTransactions = ProcurementItem::whereHas('item', function ($q) use ($id) {
            $q->where('item_type_id', $id);
        })->whereYear('created_at', $budget->year)->exists();

        if ($hasTransactions) {
            return ApiResponse::error(
                'Cannot delete budget because transactions exist for this item type in ' . $budget->year,
                400
            );
        }

        try {
            DB::beginTransaction();
            $budget->delete();
            DB::commit();

            return ApiResponse::success('Budget deleted');
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to delete budget: ' . $e->getMessage(), 500);
        }
    }
}
