<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ProductionAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductionAttributeController extends Controller
{
    private function checkAdmin($user): ?object
    {
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            return ApiResponse::error('Unauthorized: Admin access required', 403);
        }
        return null;
    }

    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $query = ProductionAttribute::orderBy('sort_order')->orderBy('id');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('name', 'like', "%$search%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return ApiResponse::success('Production attributes retrieved', $query->get());
    }

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer',
            'default_percentage' => 'nullable|integer|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        $attribute = ProductionAttribute::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'default_percentage' => $request->default_percentage ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return ApiResponse::success('Production attribute created', $attribute, 201);
    }

    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $attribute = ProductionAttribute::find($id);
        if (!$attribute) {
            return ApiResponse::error('Production attribute not found', 404);
        }

        return ApiResponse::success('Production attribute detail', $attribute);
    }

    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $attribute = ProductionAttribute::find($id);
        if (!$attribute) {
            return ApiResponse::error('Production attribute not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer',
            'default_percentage' => 'nullable|integer|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        $data = $request->only(['name', 'description', 'sort_order', 'default_percentage', 'is_active']);
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $attribute->update($data);

        return ApiResponse::success('Production attribute updated', $attribute);
    }

    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $attribute = ProductionAttribute::find($id);
        if (!$attribute) {
            return ApiResponse::error('Production attribute not found', 404);
        }

        // Check if already used in process statuses
        if ($attribute->processStatuses()->count() > 0) {
            return ApiResponse::error('Cannot delete: Attribute is already used in production logs. Deactivate it instead.', 422);
        }

        $attribute->delete();

        return ApiResponse::success('Production attribute deleted');
    }
}
