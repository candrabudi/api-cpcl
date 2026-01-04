<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\AnnualBudget;
use App\Models\AnnualBudgetTransaction;
use App\Models\PlenaryMeetingItem;
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
            'items' => function ($query) {
                $query->with([
                    'statusLogs',
                    'processStatuses',
                    'plenaryMeetingItem.item',
                    'plenaryMeetingItem.cooperative',
                    'vendor',
                ]);
            },
        ])->where('id', $id)->first();

        if (!$procurement) {
            return ApiResponse::error('Procurement not found', 404);
        }

        $this->recalcBudget($procurement);

        $totalPaid = $procurement->items->sum('total_price');

        $item = $procurement->items->first();
        $plenaryItem = $item?->plenaryMeetingItem;

        $cooperative = $plenaryItem?->cooperative;
        if ($cooperative) {
            $cooperative->total_procurement = $item?->total_price;
        }

        $extra = [
            'cooperative' => $cooperative,
            'product' => $plenaryItem?->item?->name,
            'vendor' => $item?->vendor,
        ];

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
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        DB::beginTransaction();

        try {
            $annualBudget = AnnualBudget::firstOrCreate(
                ['budget_year' => Carbon::now()->year],
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

            $itemErrors = [];

            foreach ($request->items as $index => $item) {
                $meetingItem = PlenaryMeetingItem::find($item['plenary_meeting_item_id']);

                if (!$meetingItem) {
                    $itemErrors["items.$index.plenary_meeting_item_id"][] =
                        'Invalid plenary meeting item.';
                    continue;
                }

                if (!$meetingItem->package_quantity || !$meetingItem->unit_price) {
                    $itemErrors["items.$index.plenary_meeting_item_id"][] =
                        'Package quantity or unit price is not defined.';
                    continue;
                }

                $exists = ProcurementItem::where(
                    'plenary_meeting_item_id',
                    $item['plenary_meeting_item_id']
                )->exists();

                if ($exists) {
                    $itemErrors["items.$index.plenary_meeting_item_id"][] =
                        'This item already exists in another submission.';
                    continue;
                }

                $quantity = $meetingItem->package_quantity;
                $unitPrice = $meetingItem->unit_price;
                $totalPrice = $quantity * $unitPrice;

                $procItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $meetingItem->id,
                    'vendor_id' => $item['vendor_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'delivery_status' => 'pending',
                    'process_status' => 'pending',
                ]);

                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'procurement_item_id' => $procItem->id,
                    'amount' => $totalPrice,
                ]);

                ProcurementItemProcessStatus::create([
                    'procurement_item_id' => $procItem->id,
                    'status' => 'pending',
                    'changed_by' => $request->user()->id,
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);

                ProcurementItemStatusLog::create([
                    'procurement_item_id' => $procItem->id,
                    'old_delivery_status' => null,
                    'new_delivery_status' => 'pending',
                    'changed_by' => $request->user()->id,
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);
            }

            if (!empty($itemErrors)) {
                DB::rollBack();

                return ApiResponse::validationError($itemErrors);
            }

            $this->recalcBudget($procurement);

            DB::commit();

            return ApiResponse::success(
                'Procurement created successfully',
                $procurement->load('items')
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error(
                'Failed to create procurement: '.$e->getMessage(),
                500
            );
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
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',
            'items.*.plenary_meeting_item_id' => 'required|exists:plenary_meeting_items,id',
            'items.*.vendor_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $annualBudget = AnnualBudget::where('budget_year', Carbon::now()->year)->firstOrFail();

            $procurement->update([
                'plenary_meeting_id' => $request->plenary_meeting_id,
                'procurement_number' => $request->procurement_number,
                'procurement_date' => Carbon::parse($request->procurement_date),
                'notes' => $request->notes,
                'annual_budget_id' => $annualBudget->id,
            ]);

            $procurement->items()->delete();

            foreach ($request->items as $item) {
                $meetingItem = PlenaryMeetingItem::findOrFail($item['plenary_meeting_item_id']);

                if (!$meetingItem->package_quantity || !$meetingItem->unit_price) {
                    return ApiResponse::error(
                        'Package quantity or unit price not defined for plenary meeting item ID '.$meetingItem->id,
                        422
                    );
                }

                $quantity = $meetingItem->package_quantity;
                $unitPrice = $meetingItem->unit_price;
                $totalPrice = $quantity * $unitPrice;

                $procItem = ProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'plenary_meeting_item_id' => $meetingItem->id,
                    'vendor_id' => $item['vendor_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'delivery_status' => 'pending',
                    'process_status' => 'pending',
                ]);

                AnnualBudgetTransaction::create([
                    'annual_budget_id' => $annualBudget->id,
                    'procurement_item_id' => $procItem->id,
                    'amount' => $totalPrice,
                ]);

                ProcurementItemProcessStatus::create([
                    'procurement_item_id' => $procItem->id,
                    'status' => 'pending',
                    'changed_by' => $request->user()->id,
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);

                ProcurementItemStatusLog::create([
                    'procurement_item_id' => $procItem->id,
                    'old_delivery_status' => null,
                    'new_delivery_status' => 'pending',
                    'changed_by' => $request->user()->id,
                    'status_date' => Carbon::now()->format('Y-m-d'),
                ]);
            }

            $this->recalcBudget($procurement);

            DB::commit();

            return ApiResponse::success(
                'Procurement updated',
                $procurement->load('items')
            );
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
