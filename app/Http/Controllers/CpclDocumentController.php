<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\CpclDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CpclDocumentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);

        $cacheKey = "cpcl_documents_list_page_{$page}_per_{$perPage}";

        try {
            $data = Cache::remember($cacheKey, 60, function () use ($perPage) {
                return CpclDocument::select(
                    'id',
                    'cpcl_number',
                    'title',
                    'year',
                    'cpcl_month',
                    'status',
                    'version',
                    'created_at'
                )
                    ->where('prepared_by', Auth::user()->id)
                    ->orderByDesc('id')
                    ->paginate($perPage);
            });
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve CPCL documents', 500);
        }

        return ApiResponse::success('CPCL documents retrieved', $data);
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document id', 400);
        }

        $cacheKey = 'cpcl_document_'.$id;

        $document = Cache::remember($cacheKey, 60, function () use ($id) {
            return CpclDocument::where('id', $id)->first();
        });

        if (!$document) {
            return ApiResponse::error('Document not found', 400);
        }

        return ApiResponse::success('CPCL document detail', $document);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'program_code' => 'nullable|string|max:50',
            'year' => 'required|digits:4|integer|min:2000|max:2100',
            'cpcl_date' => 'required|date',
            'cpcl_month' => 'required|integer|min:1|max:12',
            'prepared_by' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $document = CpclDocument::create([
                'cpcl_number' => null,
                'title' => trim($request->title),
                'program_code' => $request->program_code ? trim($request->program_code) : null,
                'year' => $request->year,
                'cpcl_date' => Carbon::parse($request->cpcl_date),
                'cpcl_month' => $request->cpcl_month,
                'status' => 'draft',
                'pleno_result' => 'pending',
                'version' => 1,
                'prepared_by' => $request->prepared_by ?? Auth::user()->id,
            ]);

            Cache::forget('cpcl_documents_list');

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create CPCL document', 500);
        }

        return ApiResponse::success('CPCL document created', [
            'id' => $document->id,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document id', 400);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'program_code' => 'nullable|string|max:50',
            'status' => 'sometimes|required|in:draft,submitted,review,pleno,approved,rejected,archived',
            'pleno_result' => 'sometimes|required|in:pending,approved,revision,rejected',
            'pleno_notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $document = CpclDocument::where('id', $id)->lockForUpdate()->first();

            if (!$document) {
                DB::rollBack();

                return ApiResponse::error('Document not found', 400);
            }

            $payload = [];

            foreach (['title', 'program_code', 'status', 'pleno_result', 'pleno_notes'] as $field) {
                if ($request->has($field)) {
                    $payload[$field] = $request->$field;
                }
            }

            if (isset($payload['status']) && $payload['status'] !== $document->status) {
                $payload['version'] = $document->version + 1;
            }

            $document->update($payload);

            Cache::forget('cpcl_documents_list');
            Cache::forget('cpcl_document_'.$id);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update CPCL document', 500);
        }

        return ApiResponse::success('CPCL document updated');
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document id', 400);
        }

        try {
            $document = CpclDocument::where('id', $id)->first();

            if (!$document) {
                return ApiResponse::error('Document not found', 400);
            }

            $document->delete();

            Cache::forget('cpcl_documents_list');
            Cache::forget('cpcl_document_'.$id);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete CPCL document', 500);
        }

        return ApiResponse::success('CPCL document deleted');
    }

    public function updateStatus(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document id', 400);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,submitted,review,pleno,approved,rejected,archived',
            'pleno_date' => 'nullable|date',
            'pleno_notes' => 'nullable|string|max:5000',
            'approved_by' => 'nullable|integer',
            'verified_by' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $document = CpclDocument::where('id', $id)->lockForUpdate()->first();

            if (!$document) {
                DB::rollBack();

                return ApiResponse::error('Document not found', 400);
            }

            if (in_array($document->status, ['approved', 'archived'])) {
                DB::rollBack();

                return ApiResponse::error('Document status is locked', 409);
            }

            $allowedTransitions = [
                'draft' => ['submitted'],
                'submitted' => ['review'],
                'review' => ['pleno'],
                'pleno' => ['approved', 'rejected'],
            ];

            $currentStatus = $document->status;
            $newStatus = $request->status;

            if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
                DB::rollBack();

                return ApiResponse::error('Invalid status transition', 422);
            }

            $payload = [
                'status' => $newStatus,
                'version' => $document->version + 1,
            ];

            if ($newStatus === 'submitted') {
                $payload['submitted_date'] = Carbon::today();
            }

            if ($newStatus === 'pleno') {
                if (!$request->pleno_date) {
                    DB::rollBack();

                    return ApiResponse::error('Pleno date is required', 422);
                }
                $payload['pleno_date'] = Carbon::parse($request->pleno_date);
                $payload['verified_by'] = Auth::user()->id;
                $payload['verified_at'] = Carbon::now();
            }

            if ($newStatus === 'approved') {
                $payload['approved_by'] = Auth::user()->id;
                $payload['approved_at'] = Carbon::now();
                $payload['pleno_result'] = 'approved';
            }

            if ($newStatus === 'rejected') {
                if (!$request->pleno_notes) {
                    DB::rollBack();

                    return ApiResponse::error('Pleno notes is required', 422);
                }
                $payload['pleno_notes'] = $request->pleno_notes;
                $payload['pleno_result'] = 'rejected';
            }

            $document->update($payload);

            Cache::forget('cpcl_documents_list');
            Cache::forget('cpcl_document_'.$id);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update document status', 500);
        }

        return ApiResponse::success('Document status updated');
    }
}
