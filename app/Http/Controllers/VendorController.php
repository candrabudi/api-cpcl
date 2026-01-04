<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ProcurementItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    private function recalcVendorTotals(Vendor $vendor)
    {
        $totalPaid = ProcurementItem::where('vendor_id', $vendor->id)
            ->sum('total_price');

        $vendor->total_paid = $totalPaid;
        $vendor->save();
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $query = Vendor::with(['user', 'area', 'documents.documentType'])->orderByDesc('id');

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

        // Sertakan dokumen di response
        $data->getCollection()->transform(function ($vendor) {
            $vendor->documents = $vendor->documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'document_type' => $doc->documentType->name ?? null,
                    'file_path' => $doc->file_path ? request()->getSchemeAndHttpHost().'/storage/'.$doc->file_path : null,
                    'url' => $doc->file_path ? request()->getSchemeAndHttpHost().'/storage/'.$doc->file_path : null,
                ];
            });

            return $vendor;
        });

        return ApiResponse::success('Vendors retrieved', $data);
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid vendor id', 400);
        }

        $vendor = Vendor::with(['user', 'area', 'documents.documentType'])->find($id);

        if (!$vendor) {
            return ApiResponse::error('Vendor not found', 400);
        }

        $this->recalcVendorTotals($vendor);

        // Sertakan dokumen di response
        $vendor->documents = $vendor->documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'document_type' => $doc->documentType->name ?? null,
                'file_path' => $doc->file_path,
                'url' => $doc->file_path ? request()->getSchemeAndHttpHost().'/storage/'.$doc->file_path : null,
            ];
        });

        return ApiResponse::success('Vendor detail', $vendor);
    }

    public function showWithProcurements(Request $request, $vendorID)
    {
        $vendor = Vendor::with(['documents.documentType'])->where('id', $vendorID)->first();

        if (!$vendor) {
            return ApiResponse::error('Vendor not found', 404);
        }

        $vendorId = $vendorID;

        $itemsQuery = ProcurementItem::with('procurement')
            ->where('vendor_id', $vendorId);

        if ($request->filled('search')) {
            $search = $request->search;
            $itemsQuery->whereHas('procurement', function ($q) use ($search) {
                $q->where('procurement_number', 'like', "%$search%");
            });
        }

        $items = $itemsQuery->get();

        // Total seluruh pembayaran untuk vendor
        $totalPaid = $items->sum('total_price');

        // Group per procurement, hanya tampilkan total
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

        // Sertakan dokumen vendor
        $vendorDocuments = $vendor->documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'document_type' => $doc->documentType->name ?? null,
                'file_path' => $doc->file_path,
                'url' => $doc->file_path ? request()->getSchemeAndHttpHost().'/storage/'.$doc->file_path : null,
            ];
        });

        return ApiResponse::success('Vendor procurement summary retrieved', [
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'npwp' => $vendor->npwp,
                'contact_person' => $vendor->contact_person,
                'phone' => $vendor->phone,
                'email' => $vendor->email,
                'address' => $vendor->address,
                'total_paid' => $totalPaid,
                'documents' => $vendorDocuments,
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
            'statusLogs',
            'processStatuses',
        ])
        ->where('vendor_id', $vendorID)
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
                'status_logs' => $item->statusLogs->map(function ($log) {
                    return [
                        'old_delivery_status' => $log->old_delivery_status,
                        'new_delivery_status' => $log->new_delivery_status,
                        'area_id' => $log->area_id,
                        'status_date' => $log->status_date,
                        'changed_by' => $log->changed_by,
                        'notes' => $log->notes,
                    ];
                }),
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
            'documents' => 'nullable|array',
            'documents.*.document_type_id' => 'required_with:documents|exists:document_types,id',
            'documents.*.file' => 'required_with:documents|file|mimes:pdf,jpg,jpeg,png|max:5120',
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

            $email = $request->email ?? $baseUsername.'@example.com';
            $defaultPassword = 'Vendor1234!';

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
                'npwp' => $request->npwp ?? null,
                'contact_person' => $request->contact_person ?? null,
                'phone' => $request->phone ?? null,
                'email' => $email,
                'address' => $request->address ?? null,
            ]);

            // Simpan dokumen jika ada
            if ($request->has('documents')) {
                foreach ($request->documents as $doc) {
                    if (isset($doc['file'])) {
                        // Lumen + Flysystem v2 compatible
                        $path = Storage::disk('public')->putFile('vendor_docs', $doc['file']);
                        $vendor->documents()->create([
                            'document_type_id' => $doc['document_type_id'],
                            'file_path' => $path,
                        ]);
                    }
                }
            }

            $this->recalcVendorTotals($vendor);

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

        $vendor = Vendor::with('user', 'documents')->find($id);
        if (!$vendor) {
            return ApiResponse::error('Vendor not found', 400);
        }

        // Cek duplicate npwp (di luar vendor ini)
        if ($request->filled('npwp')) {
            $existsNpwp = Vendor::where('npwp', $request->npwp)
                ->where('id', '!=', $id)
                ->exists();
            if ($existsNpwp) {
                return ApiResponse::validationError([
                    'npwp' => ['NPWP sudah digunakan oleh vendor lain.'],
                ]);
            }
        }

        // Cek duplicate email di user (di luar user ini)
        if ($request->filled('email')) {
            $existsEmail = User::where('email', $request->email)
                ->where('id', '!=', $vendor->user_id)
                ->exists();
            if ($existsEmail) {
                return ApiResponse::validationError([
                    'email' => ['Email sudah digunakan oleh user lain.'],
                ]);
            }
        }

        $validator = Validator::make($request->all(), [
            'area_id' => 'required|exists:areas,id',
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'documents' => 'nullable|array',
            'documents.*.document_type_id' => 'required_with:documents|exists:document_types,id',
            'documents.*.file' => 'required_with:documents|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Update user email
            $vendor->user->update([
                'email' => $request->email ?? $vendor->user->email,
            ]);

            // Update vendor data
            $vendor->update([
                'area_id' => $request->area_id,
                'name' => $request->name,
                'npwp' => $request->npwp ?? null,
                'contact_person' => $request->contact_person ?? null,
                'phone' => $request->phone ?? null,
                'email' => $request->email ?? $vendor->email,
                'address' => $request->address ?? null,
            ]);

            if ($request->has('documents')) {
                foreach ($request->documents as $doc) {
                    if (isset($doc['file'])) {
                        $path = Storage::disk('public')->putFile('vendor_docs', $doc['file']);
                        $vendor->documents()->create([
                            'document_type_id' => $doc['document_type_id'],
                            'file_path' => $path,
                        ]);
                    }
                }
            }

            $this->recalcVendorTotals($vendor);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update vendor: '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Vendor updated');
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
