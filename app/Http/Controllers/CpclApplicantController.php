<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Area;
use App\Models\Cooperative;
use App\Models\CpclApplicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CpclApplicantController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cpcl_document_id' => 'nullable|integer|exists:cpcl_documents,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $perPage = (int) $request->get('per_page', 10);

            $query = CpclApplicant::with(['cooperative', 'area'])
                ->orderByDesc('id');

            if ($request->filled('cpcl_document_id')) {
                $query->where('cpcl_document_id', $request->cpcl_document_id);
            }

            $data = $query->paginate($perPage);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve CPCL applicants', 500);
        }

        return ApiResponse::success('CPCL applicants retrieved', $data);
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid applicant id', 400);
        }

        $applicant = CpclApplicant::with(['cooperative', 'area'])
            ->where('cpcl_document_id', $id)
            ->first();

        if (!$applicant) {
            return ApiResponse::error('Applicant not found', 404);
        }

        return ApiResponse::success('CPCL applicant detail', $applicant);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cpcl_document_id' => 'required|integer|exists:cpcl_documents,id',
            'established_year' => 'nullable|integer|digits:4|min:1900|max:'.date('Y'),
            'group_name' => 'required|string|max:255',
            'cooperative_registration_number' => 'nullable|string|max:255',
            'kusuka_id_number' => 'nullable|string|max:255',
            'street_address' => 'nullable|string|max:255',
            'village' => 'string|required',
            'district' => 'string|required',
            'regency' => 'string|required',
            'province' => 'required',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'member_count' => 'nullable|integer|min:0',
            'chairman_name' => 'nullable|string|max:255',
            'secretary_name' => 'nullable|string|max:255',
            'treasurer_name' => 'nullable|string|max:255',
            'chairman_phone_number' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        $area = Area::where('city_name', $request->regency)
            ->where('district_name', $request->district)
            ->where('sub_district_name', $request->village)
            ->where('province_name', $request->province)
            ->first();

        if (!$area) {
            return ApiResponse::error('Invalid area.', 400);
        }

        try {
            DB::beginTransaction();

            $cooperative = Cooperative::updateOrCreate(
                [
                    'registration_number' => $request->cooperative_registration_number,
                ],
                [
                    'name' => trim($request->group_name),
                    'kusuka_id_number' => $request->kusuka_id_number,
                    'street_address' => $request->street_address,
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
                ]
            );

            $applicant = CpclApplicant::create([
                'cpcl_document_id' => $request->cpcl_document_id,
                'cooperative_id' => $cooperative->id,
                'area_id' => $area->id,
                'established_year' => $request->established_year,
                'group_name' => trim($request->group_name),
                'cooperative_registration_number' => $request->cooperative_registration_number,
                'kusuka_id_number' => $request->kusuka_id_number,
                'street_address' => $request->street_address,
                'village' => $request->village,
                'district' => $request->district,
                'regency' => $request->regency,
                'province' => $request->province,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'member_count' => $request->member_count ?? 0,
                'chairman_name' => $request->chairman_name,
                'secretary_name' => $request->secretary_name,
                'treasurer_name' => $request->treasurer_name,
                'chairman_phone_number' => $request->chairman_phone_number,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create applicant '.$e->getMessage(), 500);
        }

        return ApiResponse::success(
            'CPCL applicant created',
            ['id' => $applicant->id],
            201
        );
    }

    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid applicant id', 400);
        }

        $validator = Validator::make($request->all(), [
            'established_year' => 'nullable|integer|digits:4|min:1900|max:'.date('Y'),
            'group_name' => 'sometimes|required|string|max:255',
            'cooperative_registration_number' => 'nullable|string|max:255',
            'kusuka_id_number' => 'nullable|string|max:255',
            'street_address' => 'nullable|string|max:255',
            'village' => 'string|required',
            'district' => 'string|required',
            'regency' => 'string|required',
            'province' => 'string|required',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'member_count' => 'nullable|integer|min:0',
            'chairman_name' => 'nullable|string|max:255',
            'secretary_name' => 'nullable|string|max:255',
            'treasurer_name' => 'nullable|string|max:255',
            'chairman_phone_number' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        $area = Area::where('province_name', $request->province)
            ->where('city_name', $request->regency)
            ->where('district_name', $request->district)
            ->where('sub_district_name', $request->village)
            ->first();

        if (!$area) {
            return ApiResponse::error('Invalid area.', 400);
        }

        try {
            DB::beginTransaction();

            $applicant = CpclApplicant::lockForUpdate()->find($id);

            if (!$applicant) {
                DB::rollBack();

                return ApiResponse::error('Applicant not found', 404);
            }

            if ($applicant->cooperative_id) {
                Cooperative::where('id', $applicant->cooperative_id)->update([
                    'name' => $request->group_name ?? $applicant->group_name,
                    'registration_number' => $request->cooperative_registration_number,
                    'kusuka_id_number' => $request->kusuka_id_number,
                    'street_address' => $request->street_address,
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
                ]);
            }

            $applicant->update([
                'area_id' => $area->id,
                'established_year' => $request->established_year,
                'group_name' => $request->group_name ?? $applicant->group_name,
                'cooperative_registration_number' => $request->cooperative_registration_number,
                'kusuka_id_number' => $request->kusuka_id_number,
                'street_address' => $request->street_address,
                'village' => $request->village,
                'district' => $request->district,
                'regency' => $request->regency,
                'province' => $request->province,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'member_count' => $request->member_count ?? $applicant->member_count,
                'chairman_name' => $request->chairman_name,
                'secretary_name' => $request->secretary_name,
                'treasurer_name' => $request->treasurer_name,
                'chairman_phone_number' => $request->chairman_phone_number,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update applicant '.$e->getMessage(), 500);
        }

        return ApiResponse::success('CPCL applicant updated');
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid applicant id', 400);
        }

        $applicant = CpclApplicant::find($id);

        if (!$applicant) {
            return ApiResponse::error('Applicant not found', 404);
        }

        try {
            $applicant->delete();
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete applicant', 500);
        }

        return ApiResponse::success('CPCL applicant deleted');
    }
}
