<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AnnualBudget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AnnualBudgetController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $query = AnnualBudget::query()->orderByDesc('budget_year')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('budget_year', 'like', "%{$search}%");
        }

        $data = $query->paginate($perPage);

        return ApiResponse::success('Annual budgets retrieved', $data);
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid budget id', 400);
        }

        $budget = AnnualBudget::find($id);

        if (!$budget) {
            return ApiResponse::error('Annual budget not found', 404);
        }

        return ApiResponse::success('Annual budget detail', $budget);
    }

    public function store(Request $request)
    {
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
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create annual budget: '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Annual budget created', $budget);
    }

    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid budget id', 400);
        }

        $budget = AnnualBudget::lockForUpdate()->find($id);

        if (!$budget) {
            return ApiResponse::error('Annual budget not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'total_budget' => 'required|numeric|min:'.$budget->used_budget,
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $budget->update([
                'total_budget' => $request->total_budget,
                'remaining_budget' => $request->total_budget - $budget->used_budget,
                'updated_at' => Carbon::now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update annual budget: '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Annual budget updated', $budget);
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid budget id', 400);
        }

        $budget = AnnualBudget::find($id);

        if (!$budget) {
            return ApiResponse::error('Annual budget not found', 404);
        }

        if ($budget->used_budget > 0) {
            return ApiResponse::error('Cannot delete budget that already has usage', 422);
        }

        try {
            DB::beginTransaction();
            $budget->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to delete annual budget: '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Annual budget deleted');
    }
}
