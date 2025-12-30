<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ProcurementItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        $query = Vendor::with('user', 'area')->orderByDesc('id');

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

        $vendor = Vendor::with('user', 'area')->find($id);

        if (!$vendor) {
            return ApiResponse::error('Vendor not found', 400);
        }

        $this->recalcVendorTotals($vendor);

        return ApiResponse::success('Vendor detail', $vendor);
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

        $vendor = Vendor::with('user')->find($id);
        if (!$vendor) {
            return ApiResponse::error('Vendor not found', 400);
        }

        $validator = Validator::make($request->all(), [
            'area_id' => 'required|exists:areas,id',
            'name' => 'required|string|max:255',
            'npwp' => 'nullable|string|unique:vendors,npwp,'.$id,
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $vendor->user->update([
                'email' => $request->email ?? $vendor->user->email,
            ]);

            $vendor->update([
                'area_id' => $request->area_id,
                'name' => $request->name,
                'npwp' => $request->npwp ?? null,
                'contact_person' => $request->contact_person ?? null,
                'phone' => $request->phone ?? null,
                'email' => $request->email ?? $vendor->email,
                'address' => $request->address ?? null,
            ]);

            $this->recalcVendorTotals($vendor);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update vendor', 500);
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
