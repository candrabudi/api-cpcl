<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AnnualBudget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AnnualBudgetController extends Controller
{
    /**
     * Check if user is admin/superadmin
     * SECURITY: Only admin can manage annual budgets
     */
    private function checkAdmin($user): ?object
    {
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            \Log::warning('Unauthorized annual budget access attempt', [
                'user_id' => $user?->id ?? 'anonymous',
                'role' => $user?->role ?? 'none',
                'ip' => request()->ip()
            ]);
            return ApiResponse::error('Unauthorized: Admin access required', 403);
        }
        return null;
    }

    /**
     * List all annual budgets
     * SECURITY: Admin only
     */
    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        try {
            $perPage = min((int) $request->get('per_page', 10), 100);

            $query = AnnualBudget::query()->orderByDesc('budget_year')->orderByDesc('id');

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where('budget_year', 'like', "%{$search}%");
            }

            $data = $query->paginate($perPage);

            return ApiResponse::success('Annual budgets retrieved', $data);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve annual budgets', [
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve annual budgets', 500);
        }
    }

    /**
     * Show annual budget detail
     */
    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid budget ID', 400);
        }

        try {
            $budget = AnnualBudget::find($id);

            if (!$budget) {
                return ApiResponse::error('Annual budget not found', 404);
            }

            return ApiResponse::success('Annual budget detail', $budget);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve budget detail', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve budget details', 500);
        }
    }

    /**
     * Create new annual budget
     * TRANSACTION: Protected create operation
     * SECURITY: Admin only
     */
    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'budget_year' => 'required|digits:4|unique:annual_budgets,budget_year',
            'total_budget' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $budget = AnnualBudget::create([
                'budget_year' => $request->budget_year,
                'total_budget' => $request->total_budget,
                'used_budget' => 0,
                'remaining_budget' => $request->total_budget,
            ]);

            DB::commit();

            \Log::info('Annual budget created successfully', [
                'budget_id' => $budget->id,
                'year' => $budget->budget_year,
                'amount' => $budget->total_budget,
                'created_by' => Auth::id()
            ]);

            return ApiResponse::success('Annual budget created', $budget, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to create annual budget', [
                'year' => $request->budget_year,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to create annual budget', 500);
        }
    }

    /**
     * Update annual budget
     * TRANSACTION: Protected update operation with lock
     * SECURITY: Admin only
     */
    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid budget ID', 400);
        }

        try {
            DB::beginTransaction();

            // Lock for update must be inside transaction
            $budget = AnnualBudget::lockForUpdate()->find($id);

            if (!$budget) {
                DB::rollBack();
                return ApiResponse::error('Annual budget not found', 404);
            }

            $validator = Validator::make($request->all(), [
                'total_budget' => 'required|numeric|min:' . $budget->used_budget,
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return ApiResponse::validationError($validator->errors()->toArray());
            }

            $oldTotal = $budget->total_budget;
            $budget->update([
                'total_budget' => $request->total_budget,
                'remaining_budget' => $request->total_budget - $budget->used_budget,
            ]);

            DB::commit();

            \Log::info('Annual budget updated successfully', [
                'budget_id' => $id,
                'old_total' => $oldTotal,
                'new_total' => $budget->total_budget,
                'updated_by' => Auth::id()
            ]);

            return ApiResponse::success('Annual budget updated', $budget);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to update annual budget', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to update annual budget', 500);
        }
    }

    /**
     * Delete annual budget
     * TRANSACTION: Protected delete operation
     * SECURITY: Admin only
     */
    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid budget ID', 400);
        }

        try {
            DB::beginTransaction();

            $budget = AnnualBudget::find($id);

            if (!$budget) {
                DB::rollBack();
                return ApiResponse::error('Annual budget not found', 404);
            }

            if ($budget->used_budget > 0) {
                DB::rollBack();
                return ApiResponse::error('Cannot delete budget that already has usage', 422);
            }

            $year = $budget->budget_year;
            $budget->delete();

            DB::commit();

            \Log::info('Annual budget deleted', [
                'budget_id' => $id,
                'year' => $year,
                'deleted_by' => Auth::id()
            ]);

            return ApiResponse::success('Annual budget deleted');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to delete annual budget', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to delete annual budget', 500);
        }
    }
}
