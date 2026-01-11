<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AnnualBudget;
use App\Models\AnnualBudgetTransaction;
use App\Models\PlenaryMeetingItem;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\ProcurementItemProcessStatus;
use App\Models\ItemTypeBudget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcurementController extends Controller
{
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


    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $perPage = min((int) $request->get('per_page', 15), 100);
        
        $query = Procurement::with(['vendor', 'items.plenaryMeetingItem.item', 'creator'])
            ->orderByDesc('id');

        if ($request->get('filter') === 'archived') {
            $query->onlyTrashed();
        } elseif ($request->get('show_archived') === 'true') {
            $query->withTrashed();
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('procurement_number', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $allowedStatuses = ['draft', 'processed', 'completed', 'cancelled'];
            if (in_array($request->status, $allowedStatuses)) {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('vendor_id') && is_numeric($request->vendor_id)) {
            $query->where('vendor_id', $request->vendor_id);
        }

        return ApiResponse::success('Procurements retrieved', $query->paginate($perPage));
    }

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

        try {
            DB::beginTransaction();
            $procurement->annualBudget?->recalculateBalances();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to recalculate budget', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

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
        if ($adminCheck) return $adminCheck;

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

        try {
            DB::beginTransaction();

            $annualBudget = AnnualBudget::firstOrCreate(
                ['budget_year' => Carbon::now()->year],
                ['total_budget' => 0, 'used_budget' => 0, 'remaining_budget' => 0]
            );

            $procurement = Procurement::create([
                'vendor_id' => $request->vendor_id,
                'procurement_number' => $request->procurement_number,
                'procurement_date' => Carbon::parse($request->procurement_date),
                'notes' => $request->notes,
                'status' => 'draft',
                'annual_budget_id' => $annualBudget->id,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->items as $item) {
                $meetingItem = PlenaryMeetingItem::findOrFail($item['plenary_meeting_item_id']);

                if (!$meetingItem->package_quantity || $meetingItem->package_quantity <= 0) {
                    throw new \Exception("Package quantity not defined for plenary meeting item ID: {$meetingItem->id}");
                }

                $existingProcItem = ProcurementItem::where('plenary_meeting_item_id', $meetingItem->id)->first();
                if ($existingProcItem) {
                    throw new \Exception("Plenary meeting item ID {$meetingItem->id} is already in procurement ID {$existingProcItem->procurement_id}");
                }

                $quantity = $meetingItem->package_quantity;
                $unitPrice = $item['unit_price'];
                $totalPrice = $quantity * $unitPrice;

                $processType = $meetingItem->item->process_type ?? 'production';
                $initialProcessStatus = ($processType === 'purchase') ? 'completed' : 'pending';

                $procItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $meetingItem->id,
                    'plenary_meeting_id' => $meetingItem->plenary_meeting_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'delivery_status' => 'pending',
                    'process_status' => $initialProcessStatus,
                    'created_by' => Auth::id(),
                ]);

                $itemTypeBudget = ItemTypeBudget::where('item_type_id', $meetingItem->item_id)
                    ->where('year', $annualBudget->budget_year)
                    ->first();

                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'item_type_budget_id' => $itemTypeBudget?->id,
                    'procurement_item_id' => $procItem->id,
                    'amount' => $totalPrice,
                ]);

                if ($processType === 'production') {
                    ProcurementItemProcessStatus::create([
                        'procurement_item_id' => $procItem->id,
                        'status' => 'pending',
                        'changed_by' => Auth::id(),
                        'status_date' => Carbon::now()->format('Y-m-d'),
                    ]);
                }
            }

            $procurement->annualBudget?->recalculateBalances();

            DB::commit();

            return ApiResponse::success('Procurement created', $procurement->load('items'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create procurement: ' . $e->getMessage(), 500);
        }
    }

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

        try {
            DB::beginTransaction();

            $annualBudget = AnnualBudget::where('budget_year', Carbon::now()->year)->firstOrFail();

            $procurement->update([
                'vendor_id' => $request->vendor_id,
                'procurement_number' => $request->procurement_number,
                'procurement_date' => Carbon::parse($request->procurement_date),
                'notes' => $request->notes,
                'status' => $request->status ?? $procurement->status,
                'annual_budget_id' => $annualBudget->id,
            ]);

            $oldItemIds = $procurement->items()->pluck('id')->toArray();
            if (!empty($oldItemIds)) {
                AnnualBudgetTransaction::whereIn('procurement_item_id', $oldItemIds)->delete();
                ProcurementItemProcessStatus::whereIn('procurement_item_id', $oldItemIds)->delete();
            }
            $procurement->items()->delete();

            foreach ($request->items as $item) {
                $meetingItem = PlenaryMeetingItem::findOrFail($item['plenary_meeting_item_id']);
                
                $quantity = $meetingItem->package_quantity ?? 0;
                if ($quantity <= 0) {
                    throw new \Exception("Invalid package quantity for plenary meeting item ID: {$meetingItem->id}");
                }

                $unitPrice = $item['unit_price'];
                $totalPrice = $quantity * $unitPrice;

                $processType = $meetingItem->item->process_type ?? 'production';
                $initialProcessStatus = ($processType === 'purchase') ? 'completed' : 'pending';

                $procItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $meetingItem->id,
                    'plenary_meeting_id' => $meetingItem->plenary_meeting_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'delivery_status' => 'pending',
                    'process_status' => $initialProcessStatus,
                    'created_by' => Auth::id(),
                ]);

                $itemTypeBudget = ItemTypeBudget::where('item_type_id', $meetingItem->item_id)
                    ->where('year', $annualBudget->budget_year)
                    ->first();

                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'item_type_budget_id' => $itemTypeBudget?->id,
                    'procurement_item_id' => $procItem->id,
                    'amount' => $totalPrice,
                ]);

                if ($processType === 'production') {
                    ProcurementItemProcessStatus::create([
                        'procurement_item_id' => $procItem->id,
                        'status' => 'pending',
                        'changed_by' => Auth::id(),
                        'status_date' => Carbon::now()->format('Y-m-d'),
                    ]);
                }
            }

            $procurement->annualBudget?->recalculateBalances();

            DB::commit();

            return ApiResponse::success('Procurement updated', $procurement->load('items'));
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update procurement: ' . $e->getMessage(), 500);
        }
    }

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

        try {
            DB::beginTransaction();

            $procurement->delete();
            $procurement->annualBudget?->recalculateBalances();

            DB::commit();

            return ApiResponse::success('Procurement deleted (archived)');
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to delete procurement: ' . $e->getMessage(), 500);
        }
    }
}
