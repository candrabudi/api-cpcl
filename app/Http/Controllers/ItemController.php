<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    private function checkAdmin($user)
    {
        if ($user->role !== 'admin' && $user->role !== 'superadmin') {
            return ApiResponse::error('Unauthorized', 403);
        }
        return null;
    }

    private function getOrCreateTypeId(string $typeName): int
    {
        $camelName = Str::camel($typeName);
        $itemType = ItemType::firstOrCreate(
            ['name' => $camelName],
            ['name' => $camelName]
        );
        return $itemType->id;
    }

    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $perPage = (int) $request->get('per_page', 15);
        $query = Item::with('type')->orderByDesc('id');

        // Archive Filter
        if ($request->get('filter') === 'archived') {
            $query->onlyTrashed();
        } elseif ($request->get('show_archived') === 'true') {
            $query->withTrashed();
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->filled('item_type_id')) {
            $query->where('item_type_id', $request->item_type_id);
        }

        $items = $query->paginate($perPage);
        return ApiResponse::success('Items retrieved', $items);
    }

    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $item = Item::withTrashed()->with(['type', 'creator'])->find($id);
        if (!$item) {
            return ApiResponse::error('Item not found', 404);
        }

        return ApiResponse::success('Item detail', $item);
    }

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'item_type_id' => 'required|exists:item_types,id',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $item = Item::create([
                'name' => $request->name,
                'item_type_id' => $request->item_type_id,
                'description' => $request->description,
                'unit' => $request->unit,
                'created_by' => Auth::id(),
            ]);

            return ApiResponse::success('Item created', $item->load('type'), 201);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to create item: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $item = Item::find($id);
        if (!$item) {
            return ApiResponse::error('Item not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'item_type_id' => 'required|exists:item_types,id',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            $item->update($request->only(['name', 'item_type_id', 'description', 'unit']));
            return ApiResponse::success('Item updated', $item->load('type'));
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to update item: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $item = Item::find($id);
        if (!$item) {
            return ApiResponse::error('Item not found', 404);
        }

        try {
            $item->delete();
            return ApiResponse::success('Item deleted');
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to delete item: ' . $e->getMessage(), 500);
        }
    }

    public function types(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $perPage = (int) $request->get('per_page', 15);
        $query = ItemType::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        return ApiResponse::success('Item types retrieved', $query->paginate($perPage));
    }
}
