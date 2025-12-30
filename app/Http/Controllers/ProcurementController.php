<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AnnualBudget;
use App\Models\AnnualBudgetTransaction;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcurementController extends Controller
{
    private function checkAdmin($user)
    {
        if ($user->role !== 'admin') {
            return ApiResponse::error('Unauthorized', 403);
        }

        return null;
    }

    private function recalcBudget(Procurement $procurement)
    {
        $budget = $procurement->annualBudget;
        if (!$budget) {
            return;
        }

        $totalUsed = ProcurementItem::whereHas('procurement', function ($q) use ($budget) {
            $q->where('annual_budget_id', $budget->id);
        })->sum('total_price');

        $budget->used_budget = $totalUsed;
        $budget->remaining_budget = $budget->total_budget - $totalUsed;
        $budget->save();
    }

    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $perPage = (int) $request->get('per_page', 10);

        $query = Procurement::with([
            'plenaryMeeting',
            'annualBudget',
            'items.vendor',
        ])->orderByDesc('id');

        if ($request->filled('search')) {
            $query->where('procurement_number', 'like', "%{$request->search}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::success('Procurements retrieved', $query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $procurement = Procurement::with([
            'plenaryMeeting',
            'annualBudget',
            'items.vendor',
        ])->find($id);

        if (!$procurement) {
            return ApiResponse::error('Procurement not found', 400);
        }

        $this->recalcBudget($procurement);

        return ApiResponse::success('Procurement detail', [
            'procurement' => $procurement,
            'total_paid' => $procurement->items->sum('total_price'),
            'remaining_budget' => $procurement->annualBudget?->remaining_budget,
        ]);
    }

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $validator = Validator::make($request->all(), [
            'plenary_meeting_id' => 'required|exists:plenary_meetings,id',
            'procurement_number' => 'required|string|unique:procurements,procurement_number',
            'procurement_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.plenary_meeting_item_id' => 'required|exists:plenary_meeting_items,id',
            'items.*.vendor_id' => 'required|exists:vendors,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.estimated_delivery_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $currentYear = Carbon::now()->year;

            $annualBudget = AnnualBudget::where('budget_year', $currentYear)->first();

            if (!$annualBudget) {
                return ApiResponse::error('Annual budget for current year not found', 400);
            }

            $procurement = Procurement::create([
                'plenary_meeting_id' => $request->plenary_meeting_id,
                'annual_budget_id' => $annualBudget->id,
                'procurement_number' => $request->procurement_number,
                'procurement_date' => Carbon::parse($request->procurement_date),
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $procurementItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $item['plenary_meeting_item_id'],
                    'vendor_id' => $item['vendor_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                    'estimated_delivery_date' => isset($item['estimated_delivery_date']) ? Carbon::parse($item['estimated_delivery_date']) : null,
                ]);

                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'procurement_item_id' => $procurementItem->id,
                    'amount' => $procurementItem->total_price,
                ]);
            }

            $this->recalcBudget($procurement);

            DB::commit();

            return ApiResponse::success('Procurement created', $procurement);
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create procurement: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $procurement = Procurement::with('items')->find($id);
        if (!$procurement) {
            return ApiResponse::error('Procurement not found', 400);
        }

        $validator = Validator::make($request->all(), [
            'plenary_meeting_id' => 'required|exists:plenary_meetings,id',
            'annual_budget_id' => 'required|exists:annual_budgets,id',
            'procurement_number' => 'required|string|unique:procurements,procurement_number,'.$id,
            'procurement_date' => 'required|date',
            'status' => 'nullable|in:draft,approved,contracted,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.plenary_meeting_item_id' => 'required|exists:plenary_meeting_items,id',
            'items.*.vendor_id' => 'required|exists:vendors,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.estimated_delivery_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $procurement->update([
                'plenary_meeting_id' => $request->plenary_meeting_id,
                'annual_budget_id' => $request->annual_budget_id,
                'procurement_number' => $request->procurement_number,
                'procurement_date' => Carbon::parse($request->procurement_date),
                'status' => $request->status ?? $procurement->status,
                'notes' => $request->notes,
            ]);

            $procurement->items()->delete();

            foreach ($request->items as $item) {
                ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $item['plenary_meeting_item_id'],
                    'vendor_id' => $item['vendor_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                    'estimated_delivery_date' => isset($item['estimated_delivery_date']) ? Carbon::parse($item['estimated_delivery_date']) : null,
                ]);
            }

            $this->recalcBudget($procurement);

            DB::commit();

            return ApiResponse::success('Procurement updated', $procurement);
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update procurement: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $procurement = Procurement::with('items')->find($id);
        if (!$procurement) {
            return ApiResponse::error('Procurement not found', 400);
        }

        try {
            DB::beginTransaction();

            $procurement->delete();
            $this->recalcBudget($procurement);

            DB::commit();

            return ApiResponse::success('Procurement deleted');
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to delete procurement: '.$e->getMessage(), 500);
        }
    }
}
