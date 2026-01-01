<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AnnualBudget;
use App\Models\AnnualBudgetTransaction;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\ProcurementItemProcessStatus;
use App\Models\ProcurementItemStatusLog;
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
        $budget = AnnualBudget::find($procurement->annual_budget_id ?? null);
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
            'items', // load procurement items
            'items.processStatuses', // load process status logs
            'items.statusLogs',     // load delivery status logs
        ])->orderByDesc('id');

        // Filter by procurement number
        if ($request->filled('search')) {
            $query->where('procurement_number', 'like', "%{$request->search}%");
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('procurement_date', [
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
            ]);
        }

        $procurements = $query->paginate($perPage);

        return ApiResponse::success('Procurements retrieved', $procurements);
    }

    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $procurement = Procurement::with([
            'plenaryMeeting',
            'items',
            'items.statusLogs',       // delivery status logs
            'items.processStatuses',  // process status logs
        ])->find($id);

        if (!$procurement) {
            return ApiResponse::error('Procurement not found', 404);
        }

        // Recalculate budget if needed
        $this->recalcBudget($procurement);

        // Hitung total price semua items
        $totalPaid = $procurement->items->sum('total_price');

        return ApiResponse::success('Procurement detail', [
            'procurement' => $procurement,
            'total_paid' => $totalPaid,
            'remaining_budget' => $procurement->annualBudget?->remaining_budget ?? null,
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
            'items.*.vendor_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.delivery_status' => 'nullable|string|max:255',
            'items.*.process_status' => 'nullable|in:pending,purchase,production,completed',
            'items.*.production_start_date' => 'nullable|date',
            'items.*.production_end_date' => 'nullable|date|after_or_equal:items.*.production_start_date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $currentYear = Carbon::now()->year;
            $annualBudget = AnnualBudget::firstOrCreate(
                ['budget_year' => $currentYear],
                ['total_budget' => 0, 'used_budget' => 0, 'remaining_budget' => 0]
            );

            $procurement = Procurement::create([
                'plenary_meeting_id' => $request->plenary_meeting_id,
                'procurement_number' => $request->procurement_number,
                'procurement_date' => Carbon::parse($request->procurement_date),
                'notes' => $request->notes,
                'status' => 'draft',
                'annual_budget_id' => $annualBudget->id,
            ]);

            foreach ($request->items as $item) {
                $procItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $item['plenary_meeting_item_id'],
                    'vendor_id' => $item['vendor_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                    'delivery_status' => $item['delivery_status'] ?? 'pending',
                    'process_status' => $item['process_status'] ?? 'pending',
                ]);

                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'procurement_item_id' => $procItem->id,
                    'amount' => $procItem->total_price,
                ]);

                // create initial process status log if provided
                ProcurementItemProcessStatus::create([
                    'procurement_item_id' => $procItem->id,
                    'status' => 'pending',
                    'production_start_date' => $item['production_start_date'] ?? null,
                    'production_end_date' => $item['production_end_date'] ?? null,
                    'changed_by' => $request->user()->id,
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);

                // create initial delivery status log
                ProcurementItemStatusLog::create([
                    'procurement_item_id' => $procItem->id,
                    'old_delivery_status' => null,
                    'new_delivery_status' => $procItem->delivery_status,
                    'changed_by' => $request->user()->id,
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);
            }

            $this->recalcBudget($procurement);

            DB::commit();

            return ApiResponse::success('Procurement created', $procurement->load('items'));
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
            return ApiResponse::error('Procurement not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'plenary_meeting_id' => 'required|exists:plenary_meetings,id',
            'procurement_number' => 'required|string|unique:procurements,procurement_number,'.$id,
            'procurement_date' => 'required|date',
            'status' => 'nullable|in:draft,approved,contracted,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.plenary_meeting_item_id' => 'required|exists:plenary_meeting_items,id',
            'items.*.vendor_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.delivery_status' => 'nullable|string|max:255',
            'items.*.process_status' => 'nullable|in:pending,purchase,production,completed',
            'items.*.production_start_date' => 'nullable|date',
            'items.*.production_end_date' => 'nullable|date|after_or_equal:items.*.production_start_date',
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

            $procurement->update([
                'plenary_meeting_id' => $request->plenary_meeting_id,
                'procurement_number' => $request->procurement_number,
                'procurement_date' => Carbon::parse($request->procurement_date),
                'status' => $request->status ?? $procurement->status,
                'notes' => $request->notes,
                'annual_budget_id' => $annualBudget->id,
            ]);

            $procurement->items()->delete();

            foreach ($request->items as $item) {
                $procItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $item['plenary_meeting_item_id'],
                    'vendor_id' => $item['vendor_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                    'delivery_status' => $item['delivery_status'] ?? 'pending',
                    'process_status' => $item['process_status'] ?? 'pending',
                ]);

                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'procurement_item_id' => $procItem->id,
                    'amount' => $procItem->total_price,
                ]);

                ProcurementItemProcessStatus::create([
                    'procurement_item_id' => $procItem->id,
                    'status' => $procItem->process_status,
                    'production_start_date' => $item['production_start_date'] ?? null,
                    'production_end_date' => $item['production_end_date'] ?? null,
                    'changed_by' => $request->user()->id,
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);

                ProcurementItemStatusLog::create([
                    'procurement_item_id' => $procItem->id,
                    'old_delivery_status' => null,
                    'new_delivery_status' => $procItem->delivery_status,
                    'changed_by' => $request->user()->id,
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);
            }

            $this->recalcBudget($procurement);

            DB::commit();

            return ApiResponse::success('Procurement updated', $procurement->load('items'));
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
            return ApiResponse::error('Procurement not found', 404);
        }

        try {
            DB::beginTransaction();
            $procurement->delete();
            $this->recalcBudget($procurement);

            DB::commit();

            return ApiResponse::success('Procurement deleted successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to delete procurement: '.$e->getMessage(), 500);
        }
    }
}
