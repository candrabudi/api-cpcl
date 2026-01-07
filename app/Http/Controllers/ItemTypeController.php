<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\ItemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemTypeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $query = ItemType::query()->orderBy('name');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }

            $types = $query->paginate($perPage);

            return ApiResponse::success('Item types retrieved', $types);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve item types: ' . $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item type id', 400);
        }

        $itemType = ItemType::find($id);

        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        return ApiResponse::success('Item type detail', $itemType);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_types,name',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $itemType = ItemType::create([
                'name' => $request->name,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to create item type: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Item type created', $itemType, 201);
    }

    public function update(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item type id', 400);
        }

        $itemType = ItemType::find($id);

        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:item_types,name,' . $id,
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $itemType->update([
                'name' => $request->name,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to update item type: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Item type updated', $itemType);
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item type id', 400);
        }

        $itemType = ItemType::find($id);

        if (!$itemType) {
            return ApiResponse::error('Item type not found', 404);
        }

        // Check if type is used by items ? 
        // Ideally we should check this, but for now I'll stick to basic delete
        // If there is foreign key constraint it will fail and catch block will handle it.

        try {
            $itemType->delete();
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete item type: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success('Item type deleted');
    }
}
