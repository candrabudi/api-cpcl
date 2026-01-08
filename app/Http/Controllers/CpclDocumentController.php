<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\CpclDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CpclDocumentController extends Controller
{
    private function checkAdmin($user)
    {
        if ($user->role !== 'admin' && $user->role !== 'superadmin') {
            return ApiResponse::error('Unauthorized', 403);
        }
        return null;
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        
        $query = CpclDocument::with('creator')->orderByDesc('id');

        // Archive Filter
        if ($request->get('filter') === 'archived') {
            $query->onlyTrashed();
        } elseif ($request->get('show_archived') === 'true') {
            $query->withTrashed();
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('document_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::success('CPCL documents retrieved', $query->paginate($perPage));
    }

    public function show($id)
    {
        $document = CpclDocument::withTrashed()->with(['applicants', 'answers.fieldRow', 'fishingVessels', 'creator'])->find($id);

        if (!$document) {
            return ApiResponse::error('Document not found', 404);
        }

        return ApiResponse::success('CPCL document detail', $document);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required|string|max:100|unique:cpcl_documents,document_number',
            'document_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $document = CpclDocument::create([
                'document_number' => $request->document_number,
                'document_date' => Carbon::parse($request->document_date),
                'notes' => $request->notes,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            return ApiResponse::success('CPCL document created', $document, 201);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to create CPCL document: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $document = CpclDocument::find($id);

        if (!$document) {
            return ApiResponse::error('Document not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'document_number' => 'sometimes|required|string|max:100|unique:cpcl_documents,document_number,' . $id,
            'document_date' => 'sometimes|required|date',
            'notes' => 'nullable|string',
            'status' => 'sometimes|required|in:draft,submitted,verified,approved,rejected',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $document->update($request->only(['document_number', 'document_date', 'notes', 'status']));
            return ApiResponse::success('CPCL document updated', $document);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to update CPCL document: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        $document = CpclDocument::find($id);

        if (!$document) {
            return ApiResponse::error('Document not found', 404);
        }

        try {
            $document->delete();
            return ApiResponse::success('CPCL document deleted');
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete CPCL document', 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $document = CpclDocument::find($id);

        if (!$document) {
            return ApiResponse::error('Document not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,submitted,verified,approved,rejected',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $document->update(['status' => $request->status]);
            return ApiResponse::success('Document status updated', $document);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to update document status', 500);
        }
    }
}
