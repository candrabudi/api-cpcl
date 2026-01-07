<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DocumentTypeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $query = DocumentType::query()->orderBy('name');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            }

            $data = $query->paginate($perPage);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve document types: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Document types retrieved', $data);
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document type id', 400);
        }

        $documentType = DocumentType::find($id);

        if (!$documentType) {
            return ApiResponse::error('Document type not found', 404);
        }

        return ApiResponse::success('Document type detail', $documentType);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:document_types,name',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $documentType = DocumentType::create([
                'name' => $request->name,
                'description' => $request->description,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to create document type: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Document type created', $documentType, 201);
    }

    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document type id', 400);
        }

        $documentType = DocumentType::find($id);

        if (!$documentType) {
            return ApiResponse::error('Document type not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:document_types,name,' . $id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $documentType->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to update document type: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Document type updated', $documentType);
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid document type id', 400);
        }

        $documentType = DocumentType::find($id);

        if (!$documentType) {
            return ApiResponse::error('Document type not found', 404);
        }

        try {
            $documentType->delete();
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete document type: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Document type deleted');
    }
}
