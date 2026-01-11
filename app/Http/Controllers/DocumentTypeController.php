<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentTypeController extends Controller
{
    private function checkAdmin($user): ?object
    {
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            \Log::warning('Unauthorized document type access attempt', [
                'user_id' => $user?->id ?? 'anonymous',
                'role' => $user?->role ?? 'none',
                'ip' => request()->ip()
            ]);
            return ApiResponse::error('Unauthorized: Admin access required', 403);
        }
        return null;
    }

    public function index(Request $request)
    {
        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $query = DocumentType::query()->orderBy('name');

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $data = $query->paginate($perPage);
            
            return ApiResponse::success('Document types retrieved', $data);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve document types', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return ApiResponse::error('Failed to retrieve document types', 500);
        }
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document type ID', 400);
        }

        try {
            $documentType = DocumentType::find($id);

            if (!$documentType) {
                return ApiResponse::error('Document type not found', 404);
            }

            return ApiResponse::success('Document type detail', $documentType);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve document type detail', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve document type details', 500);
        }
    }

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:document_types,name',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $documentType = DocumentType::create([
                'name' => trim($request->name),
                'description' => $request->description ? trim($request->description) : null,
            ]);

            DB::commit();

            \Log::info('Document type created successfully', [
                'document_type_id' => $documentType->id,
                'name' => $documentType->name,
                'created_by' => Auth::id()
            ]);

            return ApiResponse::success('Document type created', $documentType, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to create document type', [
                'name' => $request->name,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to create document type', 500);
        }
    }

    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document type ID', 400);
        }

        $documentType = DocumentType::find($id);

        if (!$documentType) {
            return ApiResponse::error('Document type not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:document_types,name,' . $id,
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $oldData = $documentType->toArray();
            $documentType->update([
                'name' => trim($request->name),
                'description' => $request->description ? trim($request->description) : null,
            ]);

            DB::commit();

            \Log::info('Document type updated successfully', [
                'document_type_id' => $id,
                'old_name' => $oldData['name'],
                'new_name' => $documentType->name,
                'updated_by' => Auth::id()
            ]);

            return ApiResponse::success('Document type updated', $documentType);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to update document type', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to update document type', 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document type ID', 400);
        }

        $documentType = DocumentType::find($id);

        if (!$documentType) {
            return ApiResponse::error('Document type not found', 404);
        }

        try {
            DB::beginTransaction();

            $typeName = $documentType->name;
            $documentType->delete();

            DB::commit();

            \Log::info('Document type deleted', [
                'document_type_id' => $id,
                'name' => $typeName,
                'deleted_by' => Auth::id()
            ]);

            return ApiResponse::success('Document type deleted');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to delete document type', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to delete document type', 500);
        }
    }
}
