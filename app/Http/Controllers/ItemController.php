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
        if ($user->role !== 'admin') {
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

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'code' => 'nullable|string|max:50|unique:items,code',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'specification' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $typeId = $this->getOrCreateTypeId($request->type);

            $item = Item::create(array_merge(
                $request->except('type'),
                [
                    'item_type_id' => $typeId,
                    'created_by' => Auth::user()->id,
                ]
            ));

            DB::commit();

            return ApiResponse::success('Item created', $item->load('type'));
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create item: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $item = Item::find($id);
        if (!$item) {
            return ApiResponse::error('Item not found', 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'code' => 'nullable|string|max:50|unique:items,code,'.$id,
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'specification' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $typeId = $this->getOrCreateTypeId($request->type);

            $item->update(array_merge(
                $request->except('type'),
                ['item_type_id' => $typeId]
            ));

            DB::commit();

            return ApiResponse::success('Item updated', $item->load('type'));
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update item: '.$e->getMessage(), 500);
        }
    }

    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $perPage = (int) $request->get('per_page', 15);

        $query = Item::with('type')->orderByDesc('id');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
        }

        if ($request->filled('type')) {
            $query->whereHas('type', function ($q) use ($request) {
                $q->where('name', Str::camel($request->type));
            });
        }

        $items = $query->paginate($perPage);

        $items->getCollection()->transform(function ($item) {
            $item->type_name = $item->type?->name;

            return $item;
        });

        return ApiResponse::success('Items retrieved', $items);
    }

    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $item = Item::with('type')->find($id);
        if (!$item) {
            return ApiResponse::error('Item not found', 400);
        }

        return ApiResponse::success('Item detail', $item);
    }

    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $item = Item::find($id);
        if (!$item) {
            return ApiResponse::error('Item not found', 400);
        }

        try {
            DB::beginTransaction();

            $item->delete();

            DB::commit();

            return ApiResponse::success('Item deleted');
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to delete item: '.$e->getMessage(), 500);
        }
    }

    public function types(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $types = ItemType::orderBy('name')->get(['id', 'name']);

        return ApiResponse::success('Item types retrieved', $types);
    }
}
