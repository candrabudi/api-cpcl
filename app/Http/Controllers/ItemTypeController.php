<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ItemType;
use App\Models\ItemTypeBudget;
use App\Models\ProcurementItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemTypeController extends Controller
{
    private function checkAdmin($user)
    {
        if ($user->role !== 'admin' && $user->role !== 'superadmin') {
            return ApiResponse::error('Unauthorized', 403);
        }
        return null;
    }

    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $query = ItemType::query()->orderBy('name');

            // Archive Filter
            if ($request->get('filter') === 'archived') {
                $query->onlyTrashed();
            } elseif ($request->get('show_archived') === 'true') {
                $query->withTrashed();
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }

            $types = $query->paginate($perPage);

            return ApiResponse::success('Item types retrieved', $types);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve item types: ' . $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
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
            $itemType = ItemType::create([
                'name' => $request->name,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to create item type: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Item type created', $itemType, 201);
    }

    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

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
            $itemType->update([
                'name' => $request->name,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to update item type: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Item type updated', $itemType);
    }

    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $itemType = ItemType::find($id);

        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        try {
            $itemType->delete();
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete item type: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Item type deleted');
    }

    public function getBudgets($id)
    {
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
            $budget = $itemType->budgets()->create([
                'year' => $request->year,
                'amount' => $request->amount,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to create budget: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Budget created', $budget, 201);
    }

    public function updateBudget(Request $request, $id, $budgetId)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

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
            // Check transactions: Procurement items using items of this type in this budget year
            $hasTransactions = ProcurementItem::whereHas('item', function ($q) use ($id) {
                $q->where('item_type_id', $id);
            })->whereYear('created_at', $budget->year)->exists();

            if ($hasTransactions) {
                return ApiResponse::error('Cannot update budget amount because transactions exist for this item type in ' . $budget->year, 400);
            }
        }

        try {
            $budget->update([
                'amount' => $request->amount,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to update budget: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Budget updated', $budget);
    }

    public function destroyBudget(Request $request, $id, $budgetId)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

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
            return ApiResponse::error('Cannot delete budget because transactions exist for this item type in ' . $budget->year, 400);
        }

        try {
            $budget->delete();
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete budget: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Budget deleted');
    }
}
