<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ProcurementItem;
use App\Models\ProcurementItemProcessStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProcurementVendorController extends Controller
{
    private function getVendor($user)
    {
        if (!$user->vendor) {
            return null;
        }
        return $user->vendor;
    }

    public function index(Request $request)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $query = ProcurementItem::with([
            'procurement',
            'plenaryMeetingItem.item',
            'plenaryMeetingItem.cooperative',
            'processStatuses.user',
            'shipmentItems.shipment'
        ])->whereHas('procurement', function($q) use ($vendor) {
            $q->where('vendor_id', $vendor->id);
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('procurement', function ($qp) use ($search) {
                    $qp->where('procurement_number', 'like', "%$search%");
                })
                ->orWhereHas('plenaryMeetingItem.item', function ($qi) use ($search) {
                    $qi->where('name', 'like', "%$search%");
                });
            });
        }

        $perPage = $request->get('per_page', 10);
        $items = $query->orderByDesc('id')->paginate($perPage);

        return ApiResponse::success('Vendor procurement items retrieved', $items);
    }

    public function updateProcessStatus(Request $request, $id)
    {
        $vendor = $this->getVendor($request->user());
        if (!$vendor) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $item = ProcurementItem::find($id);
        if (!$item) {
            return ApiResponse::error('Procurement item not found', 404);
        }

        // Verify ownership via procurement
        if ($item->procurement->vendor_id != $vendor->id) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $validator = Validator::make($request->all(), [
            'process_status' => 'required|in:pending,purchase,production,completed',
            'production_start_date' => 'nullable|date',
            'production_end_date' => 'nullable|date|after_or_equal:production_start_date',
            'area_id' => 'nullable|exists:areas,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        DB::beginTransaction();
        try {
            $item->process_status = $request->process_status;
            $item->save();

            ProcurementItemProcessStatus::create([
                'procurement_item_id' => $item->id,
                'status' => $request->process_status,
                'production_start_date' => $request->production_start_date ?? null,
                'production_end_date' => $request->production_end_date ?? null,
                'area_id' => $request->area_id ?? null,
                'changed_by' => $request->user()->id,
                'notes' => $request->notes ?? null,
                'status_date' => Carbon::now()->format('Y-m-d'),
            ]);

            DB::commit();

            return ApiResponse::success('Process status updated', $item->load([
                'plenaryMeetingItem.item',
                'processStatuses'
            ]));
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update process status: '.$e->getMessage(), 500);
        }
    }
}
