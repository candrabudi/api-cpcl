<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AnnualBudget;
use App\Models\AnnualBudgetAllocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AnnualBudgetAllocationController extends Controller
{
    public function store(Request $request)
    {
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

            $budget = AnnualBudget::lockForUpdate()->find($request->annual_budget_id);

            if (!$budget) {
                DB::rollBack();

                return ApiResponse::error('Annual budget not found', 404);
            }

            $totalAllocated = AnnualBudgetAllocation::where('annual_budget_id', $budget->id)
                ->sum('allocated_amount');

            if (($totalAllocated + $request->allocated_amount) > $budget->remaining_budget) {
                return ApiResponse::error('Allocation exceeds remaining budget', 422);
            }

            $allocation = AnnualBudgetAllocation::create([
                'annual_budget_id' => $budget->id,
                'allocation_name' => $request->allocation_name,
                'allocated_amount' => $request->allocated_amount,
                'used_amount' => 0,
                'remaining_amount' => $request->allocated_amount,
                'created_at' => Carbon::now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create allocation', 500);
        }

        return ApiResponse::success('Allocation created', $allocation);
    }
}
