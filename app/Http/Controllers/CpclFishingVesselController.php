<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\CpclDocument;
use App\Models\CpclFishingVessel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CpclFishingVesselController extends Controller
{
    public function show($cpclDocumentId)
    {
        if (!is_numeric($cpclDocumentId)) {
            return ApiResponse::error('Invalid document id', 400);
        }

        $cacheKey = 'cpcl_fishing_vessels_'.$cpclDocumentId;

        $data = Cache::remember($cacheKey, 60, function () use ($cpclDocumentId) {
            return CpclFishingVessel::where('cpcl_document_id', $cpclDocumentId)
                ->orderBy('id')
                ->get();
        });

        return ApiResponse::success('Fishing vessels retrieved', [
            'items' => $data,
            'total_quantity' => $data->sum('quantity'),
        ]);
    }

    public function store(Request $request, $cpclDocumentId)
    {
        if (!is_numeric($cpclDocumentId)) {
            return ApiResponse::error('Invalid document id', 400);
        }

        $validator = Validator::make($request->all(), [
            'fishing_vessels' => 'required|array|min:1',
            'fishing_vessels.*.ship_type' => 'nullable|string|max:255',
            'fishing_vessels.*.engine_brand' => 'nullable|string|max:255',
            'fishing_vessels.*.engine_power' => 'nullable|string|max:100',
            'fishing_vessels.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $document = CpclDocument::where('id', $cpclDocumentId)->lockForUpdate()->first();

            if (!$document) {
                DB::rollBack();

                return ApiResponse::error('Document not found', 400);
            }

            CpclFishingVessel::where('cpcl_document_id', $cpclDocumentId)->delete();

            foreach ($request->fishing_vessels as $row) {
                CpclFishingVessel::create([
                    'cpcl_document_id' => $cpclDocumentId,
                    'ship_type' => $row['ship_type'] ?? null,
                    'engine_brand' => $row['engine_brand'] ?? null,
                    'engine_power' => $row['engine_power'] ?? null,
                    'quantity' => $row['quantity'],
                ]);
            }

            $document->update([
                'version' => $document->version + 1,
            ]);

            Cache::forget('cpcl_documents_list');
            Cache::forget('cpcl_document_'.$cpclDocumentId);
            Cache::forget('cpcl_fishing_vessels_'.$cpclDocumentId);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to save fishing vessels', 500);
        }

        return ApiResponse::success('Fishing vessels saved');
    }

    public function update(Request $request, $cpclDocumentId)
    {
        return $this->store($request, $cpclDocumentId);
    }
}
