<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AnnualBudget;
use App\Models\AnnualBudgetAllocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AnnualBudgetAllocationController extends Controller
{
    /**
     * Check if user is admin/superadmin
     * SECURITY: Only admin can manage budget allocations
     */
    private function checkAdmin($user): ?object
    {
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            \Log::warning('Unauthorized budget allocation access attempt', [
                'user_id' => $user?->id ?? 'anonymous',
                'role' => $user?->role ?? 'none',
                'ip' => request()->ip()
            ]);
            return ApiResponse::error('Unauthorized: Admin access required', 403);
        }
        return null;
    }

    /**
     * List all allocations for a specific budget
     */
    public function index(Request $request, $budgetId)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($budgetId)) {
            return ApiResponse::error('Invalid budget ID', 400);
        }

        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $query = AnnualBudgetAllocation::where('annual_budget_id', $budgetId)->orderByDesc('id');

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where('allocation_name', 'like', "%{$search}%");
            }

            $data = $query->paginate($perPage);
            return ApiResponse::success('Budget allocations retrieved', $data);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve budget allocations', [
                'budget_id' => $budgetId,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve allocations', 500);
        }
    }

    /**
     * Show allocation detail
     */
    public function show(Request $request, $budgetId, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($budgetId) || !is_numeric($id)) {
            return ApiResponse::error('Invalid ID format', 400);
        }

        try {
            $allocation = AnnualBudgetAllocation::where('annual_budget_id', $budgetId)->find($id);

            if (!$allocation) {
                return ApiResponse::error('Budget allocation not found', 404);
            }

            return ApiResponse::success('Budget allocation detail', $allocation);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve allocation detail', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve allocation details', 500);
        }
    }

    /**
     * Create new allocation
     * TRANSACTION: Protected creation with budget check
     * SECURITY: Admin only
     */
    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'annual_budget_id' => 'required|exists:annual_budgets,id',
            'allocation_name' => 'required|string|max:255',
            'allocated_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Lock budget to prevent concurrent over-allocation
            $budget = AnnualBudget::lockForUpdate()->find($request->annual_budget_id);

            if (!$budget) {
                DB::rollBack();
                return ApiResponse::error('Annual budget not found', 404);
            }

            // Calculate current total allocated for this year
            $totalAllocated = AnnualBudgetAllocation::where('annual_budget_id', $budget->id)
                ->sum('allocated_amount');

            // BUSINESS RULE: Total allocations cannot exceed total budget
            if (($totalAllocated + $request->allocated_amount) > $budget->total_budget) {
                DB::rollBack();
                return ApiResponse::error('Allocation exceeds total annual budget availability', 422);
            }

            $allocation = AnnualBudgetAllocation::create([
                'annual_budget_id' => $budget->id,
                'allocation_name' => trim($request->allocation_name),
                'allocated_amount' => $request->allocated_amount,
                'used_amount' => 0,
                'remaining_amount' => $request->allocated_amount,
            ]);

            DB::commit();

            \Log::info('Budget allocation created', [
                'allocation_id' => $allocation->id,
                'budget_id' => $budget->id,
                'amount' => $allocation->allocated_amount,
                'created_by' => Auth::id()
            ]);

            return ApiResponse::success('Allocation created', $allocation, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to create budget allocation', [
                'budget_id' => $request->annual_budget_id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to create allocation', 500);
        }
    }

    /**
     * Update allocation
     * TRANSACTION: Protected update with budget recalculation
     * SECURITY: Admin only
     */
    public function update(Request $request, $budgetId, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($budgetId) || !is_numeric($id)) {
            return ApiResponse::error('Invalid ID format', 400);
        }

        $validator = Validator::make($request->all(), [
            'allocation_name' => 'sometimes|required|string|max:255',
            'allocated_amount' => 'sometimes|required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $allocation = AnnualBudgetAllocation::lockForUpdate()
                ->where('annual_budget_id', $budgetId)
                ->find($id);

            if (!$allocation) {
                DB::rollBack();
                return ApiResponse::error('Budget allocation not found', 404);
            }

            // BUSINESS RULE: Cannot reduce allocation below current usage
            if ($request->filled('allocated_amount') && $request->allocated_amount < $allocation->used_amount) {
                DB::rollBack();
                return ApiResponse::error('Cannot reduce allocation below current used amount (' . $allocation->used_amount . ')', 422);
            }

            if ($request->filled('allocated_amount')) {
                $budget = AnnualBudget::lockForUpdate()->find($budgetId);
                
                // Calculate total other allocations
                $otherAllocated = AnnualBudgetAllocation::where('annual_budget_id', $budgetId)
                    ->where('id', '!=', $id)
                    ->sum('allocated_amount');

                if (($otherAllocated + $request->allocated_amount) > $budget->total_budget) {
                    DB::rollBack();
                    return ApiResponse::error('Updated allocation exceeds total annual budget availability', 422);
                }

                $allocation->allocated_amount = $request->allocated_amount;
                $allocation->remaining_amount = $request->allocated_amount - $allocation->used_amount;
            }

            if ($request->filled('allocation_name')) {
                $allocation->allocation_name = trim($request->allocation_name);
            }

            $allocation->save();

            DB::commit();

            \Log::info('Budget allocation updated', [
                'allocation_id' => $id,
                'updated_by' => Auth::id()
            ]);

            return ApiResponse::success('Allocation updated', $allocation);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to update budget allocation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to update allocation', 500);
        }
    }

    /**
     * Delete allocation
     * TRANSACTION: Protected delete operation
     * SECURITY: Admin only
     */
    public function destroy(Request $request, $budgetId, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($budgetId) || !is_numeric($id)) {
            return ApiResponse::error('Invalid ID format', 400);
        }

        try {
            DB::beginTransaction();

            $allocation = AnnualBudgetAllocation::where('annual_budget_id', $budgetId)->find($id);

            if (!$allocation) {
                DB::rollBack();
                return ApiResponse::error('Budget allocation not found', 404);
            }

            // BUSINESS RULE: Cannot delete allocation if it has usage
            if ($allocation->used_amount > 0) {
                DB::rollBack();
                return ApiResponse::error('Cannot delete allocation that already has usage (' . $allocation->used_amount . ')', 422);
            }

            $name = $allocation->allocation_name;
            $allocation->delete();

            DB::commit();

            \Log::info('Budget allocation deleted', [
                'allocation_id' => $id,
                'name' => $name,
                'deleted_by' => Auth::id()
            ]);

            return ApiResponse::success('Allocation deleted');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to delete budget allocation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to delete allocation', 500);
        }
    }
}
