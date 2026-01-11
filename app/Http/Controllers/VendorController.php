<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ProcurementItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    private function recalcVendorTotals(Vendor $vendor)
    {
        try {
            DB::beginTransaction();

            $totalPaid = ProcurementItem::whereHas('procurement', function ($query) use ($vendor) {
                $query->where('vendor_id', $vendor->id);
            })->sum('total_price');

            $vendor->total_paid = $totalPaid;
            $vendor->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to recalculate vendor totals', [
                'vendor_id' => $vendor->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $query = Vendor::with(['user', 'area', 'documents.documentType'])->orderByDesc('id');

        if ($request->get('filter') === 'archived') {
            $query->onlyTrashed();
        } elseif ($request->get('show_archived') === 'true') {
            $query->withTrashed();
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('npwp', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        $data = $query->paginate($perPage);

        return ApiResponse::success('Vendors retrieved', $data);
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid vendor id', 400);
        }

        $vendor = Vendor::withTrashed()->with(['user', 'area', 'documents.documentType'])->find($id);

        if (!$vendor) {
            return ApiResponse::error('Vendor not found', 400);
        }

        try {
            $this->recalcVendorTotals($vendor);
        } catch (\Throwable $e) {
            \Log::warning('Could not recalculate vendor totals, returning existing data', [
                'vendor_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        return ApiResponse::success('Vendor detail', $vendor);
    }

    public function showWithProcurements(Request $request, $vendorID)
    {
        $vendor = Vendor::where('id', $vendorID)->first();

        if (!$vendor) {
            return ApiResponse::error('Vendor not found', 404);
        }

        $vendorId = $vendorID;

        $itemsQuery = ProcurementItem::with('procurement')
            ->whereHas('procurement', function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $itemsQuery->whereHas('procurement', function ($q) use ($search) {
                $q->where('procurement_number', 'like', "%$search%");
            });
        }

        $items = $itemsQuery->get();

        $totalPaid = $items->sum('total_price');

        $procurements = $items->groupBy('procurement_id')->map(function ($group) {
            $procurement = $group->first()->procurement;
            $procurementTotal = $group->sum('total_price');

            return [
                'procurement_id' => $procurement->id,
                'procurement_number' => $procurement->procurement_number,
                'procurement_date' => $procurement->procurement_date,
                'total_spent' => $procurementTotal,
            ];
        })->values();

        return ApiResponse::success('Vendor procurement summary retrieved', [
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'npwp' => $vendor->npwp,
                'contact_person' => $vendor->contact_person,
                'phone' => $vendor->phone,
                'email' => $vendor->email,
                'address' => $vendor->address,
                'latitude' => $vendor->latitude,
                'longitude' => $vendor->longitude,
                'total_paid' => $totalPaid,
            ],
            'procurements' => $procurements,
        ]);
    }

    public function getVendorProcurementItems(Request $request, $vendorID, $procurementID)
    {
        $items = ProcurementItem::with([
            'procurement',
            'plenaryMeetingItem.item',
            'plenaryMeetingItem.cooperative',

            'processStatuses',
        ])
        ->whereHas('procurement', function ($q) use ($vendorID) {
            $q->where('vendor_id', $vendorID);
        })
        ->where('procurement_id', $procurementID)
        ->get();

        if ($items->isEmpty()) {
            return ApiResponse::error('No items found for this vendor and procurement', 404);
        }

        $totalSpent = $items->sum('total_price');

        $procurement = $items->first()->procurement;

        $itemsData = $items->map(function ($item) {
            return [
                'procurement_item_id' => $item->id,
                'item_id' => $item->plenaryMeetingItem->item->id,
                'item_name' => $item->plenaryMeetingItem->item->name,
                'cooperative' => optional($item->plenaryMeetingItem->cooperative)->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
                'delivery_status' => $item->delivery_status,
                'process_status' => $item->process_status,

                'process_statuses' => $item->processStatuses->map(function ($status) {
                    return [
                        'status' => $status->status,
                        'production_start_date' => $status->production_start_date,
                        'production_end_date' => $status->production_end_date,
                        'area_id' => $status->area_id,
                        'changed_by' => $status->changed_by,
                        'status_date' => $status->status_date,
                        'notes' => $status->notes,
                    ];
                }),
            ];
        });

        return ApiResponse::success('Procurement items retrieved', [
            'procurement' => [
                'id' => $procurement->id,
                'procurement_number' => $procurement->procurement_number,
                'procurement_date' => $procurement->procurement_date,
                'total_spent' => $totalSpent,
            ],
            'items' => $itemsData,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'area_id' => 'required|exists:areas,id',
            'name' => 'required|string|max:255',
            'npwp' => 'nullable|string|unique:vendors,npwp',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'longitude' => 'nullable|numeric|between:-180,180',
            'documents' => 'nullable|array',
            'documents.*.document_type_id' => 'required|exists:document_types,id',
            'documents.*.file' => 'required|file|max:5120',
            'documents.*.notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $baseUsername = strtolower(preg_replace('/\s+/', '_', $request->name));
            $username = $baseUsername;
            $count = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername.$count;
                ++$count;
            }

            $email = $request->email ?? ($baseUsername . '@example.com');
            $defaultPassword = 'Vendor1234!';

            if (User::where('email', $email)->exists()) {
                return ApiResponse::validationError(['email' => ['Email already exists.']]);
            }

            $user = User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($defaultPassword),
                'role' => 'vendor',
                'status' => 1,
            ]);

            $vendor = Vendor::create([
                'user_id' => $user->id,
                'area_id' => $request->area_id,
                'name' => $request->name,
                'npwp' => $request->npwp,
                'contact_person' => $request->contact_person,
                'phone' => $request->phone,
                'email' => $email,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'total_paid' => 0,
            ]);

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $docData) {
                    $file = $docData['file'];
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $path = 'uploads/vendor_docs/' . $vendor->id;
                    $file->move(base_path('public/' . $path), $filename);

                    VendorDocument::create([
                        'vendor_id' => $vendor->id,
                        'document_type_id' => $docData['document_type_id'],
                        'file_path' => $path . '/' . $filename,
                        'notes' => $docData['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create vendor: '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Vendor created', [
            'username' => $username,
            'email' => $email,
            'password' => $defaultPassword,
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid vendor id', 400);
        }

        $vendor = Vendor::with('user')->find($id);
        if (!$vendor) {
            return ApiResponse::error('Vendor not found', 400);
        }

        $validator = Validator::make($request->all(), [
            'area_id' => 'required|exists:areas,id',
            'name' => 'required|string|max:255',
            'npwp' => 'nullable|string|unique:vendors,npwp,' . $id,
            'email' => 'nullable|email|max:100|unique:users,email,' . $vendor->user_id,
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'documents' => 'nullable|array',
            'documents.*.document_type_id' => 'required|exists:document_types,id',
            'documents.*.file' => 'required|file|max:5120',
            'documents.*.notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $newEmail = $request->email ?? $vendor->email;

            if ($newEmail !== $vendor->user->email) {
                $vendor->user->update([
                    'email' => $newEmail,
                ]);
            }

            $vendor->update([
                'area_id' => $request->area_id,
                'name' => $request->name,
                'npwp' => $request->npwp,
                'contact_person' => $request->contact_person,
                'phone' => $request->phone,
                'email' => $newEmail,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $docData) {
                    $file = $docData['file'];
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $path = 'uploads/vendor_docs/' . $vendor->id;
                    $file->move(base_path('public/' . $path), $filename);

                    VendorDocument::create([
                        'vendor_id' => $vendor->id,
                        'document_type_id' => $docData['document_type_id'],
                        'file_path' => $path . '/' . $filename,
                        'notes' => $docData['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update vendor: '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Vendor updated', $vendor->fresh(['user', 'area']));
    }

    public function restore($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid vendor id', 400);
        }

        $vendor = Vendor::onlyTrashed()->find($id);
        if (!$vendor) {
            return ApiResponse::error('Archived vendor not found', 404);
        }

        try {
            DB::beginTransaction();

            $vendor->restore();
            
            $user = User::withTrashed()->find($vendor->user_id);
            if ($user && $user->trashed()) {
                $user->restore();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to restore vendor', 500);
        }

        return ApiResponse::success('Vendor restored');
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid vendor id', 400);
        }

        $vendor = Vendor::with('user')->find($id);
        if (!$vendor) {
            return ApiResponse::error('Vendor not found', 400);
        }

        try {
            DB::beginTransaction();

            $vendor->delete();
            $vendor->user->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to delete vendor', 500);
        }

        return ApiResponse::success('Vendor deleted');
    }
}
