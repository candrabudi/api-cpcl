<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Area;
use App\Models\Cooperative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CooperativeController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $perPage = (int) $request->get('per_page', 10);

            $query = Cooperative::query()->orderByDesc('id');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('kusuka_id_number', 'like', "%{$search}%");
                });
            }

            $data = $query->paginate($perPage);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve cooperatives', 500);
        }

        return ApiResponse::success('Cooperatives retrieved', $data);
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid cooperative id', 400);
        }

        $cooperative = Cooperative::find($id);

        if (!$cooperative) {
            return ApiResponse::error('Cooperative not found', 400);
        }

        return ApiResponse::success('Cooperative detail', $cooperative);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|max:255|unique:cooperatives,registration_number',
            'kusuka_id_number' => 'nullable|string|max:255|unique:cooperatives,kusuka_id_number',
            'established_year' => 'nullable|integer|digits:4|min:1900|max:'.date('Y'),

            'street_address' => 'nullable|string|max:255',
            'village' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'regency' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',

            'phone_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',

            'chairman_name' => 'nullable|string|max:255',
            'secretary_name' => 'nullable|string|max:255',
            'treasurer_name' => 'nullable|string|max:255',
            'chairman_phone_number' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        $area = null;
        if ($request->filled(['province', 'regency', 'district', 'village'])) {
            $area = Area::where('province_name', $request->province)
                ->where('city_name', $request->regency)
                ->where('district_name', $request->district)
                ->where('sub_district_name', $request->village)
                ->first();

            if (!$area) {
                return ApiResponse::error('Invalid area.', 400);
            }
        }

        try {
            DB::beginTransaction();

            $cooperative = Cooperative::create([
                'name' => trim($request->name),
                'registration_number' => $request->registration_number,
                'kusuka_id_number' => $request->kusuka_id_number,
                'established_year' => $request->established_year,
                'street_address' => $request->street_address,
                'area_id' => $area?->id,
                'village' => $request->village,
                'district' => $request->district,
                'regency' => $request->regency,
                'province' => $request->province,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'chairman_name' => $request->chairman_name,
                'secretary_name' => $request->secretary_name,
                'treasurer_name' => $request->treasurer_name,
                'chairman_phone_number' => $request->chairman_phone_number,
                'member_count' => $request->member_count,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create cooperative '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Cooperative created', ['id' => $cooperative->id], 201);
    }

    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid cooperative id', 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'registration_number' => 'nullable|string|max:255|unique:cooperatives,registration_number,'.$id,
            'kusuka_id_number' => 'nullable|string|max:255|unique:cooperatives,kusuka_id_number,'.$id,
            'established_year' => 'nullable|integer|digits:4|min:1900|max:'.date('Y'),

            'street_address' => 'nullable|string|max:255',
            'village' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'regency' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',

            'phone_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',

            'chairman_name' => 'nullable|string|max:255',
            'secretary_name' => 'nullable|string|max:255',
            'treasurer_name' => 'nullable|string|max:255',
            'chairman_phone_number' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        $area = null;
        if ($request->filled(['province', 'regency', 'district', 'village'])) {
            $area = Area::where('province_name', $request->province)
                ->where('city_name', $request->regency)
                ->where('district_name', $request->district)
                ->where('sub_district_name', $request->village)
                ->first();

            if (!$area) {
                return ApiResponse::error('Invalid area.', 400);
            }
        }

        try {
            DB::beginTransaction();

            $cooperative = Cooperative::lockForUpdate()->find($id);

            if (!$cooperative) {
                DB::rollBack();

                return ApiResponse::error('Cooperative not found', 404);
            }

            $cooperative->update([
                'name' => $request->name ?? $cooperative->name,
                'registration_number' => $request->registration_number,
                'kusuka_id_number' => $request->kusuka_id_number,
                'established_year' => $request->established_year,
                'street_address' => $request->street_address,
                'area_id' => $area?->id,
                'village' => $request->village,
                'district' => $request->district,
                'regency' => $request->regency,
                'province' => $request->province,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'chairman_name' => $request->chairman_name,
                'secretary_name' => $request->secretary_name,
                'treasurer_name' => $request->treasurer_name,
                'chairman_phone_number' => $request->chairman_phone_number,
                'member_count' => $request->member_count,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update cooperative '.$e->getMessage(), 500);
        }

        return ApiResponse::success('Cooperative updated');
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid cooperative id', 400);
        }

        $cooperative = Cooperative::find($id);

        if (!$cooperative) {
            return ApiResponse::error('Cooperative not found', 404);
        }

        try {
            $cooperative->delete();
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete cooperative', 500);
        }

        return ApiResponse::success('Cooperative deleted');
    }
}
