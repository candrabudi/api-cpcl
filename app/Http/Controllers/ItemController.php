<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ItemController extends Controller
{
    private function checkAdmin($user)
    {
        if ($user->role !== 'admin') {
            return ApiResponse::error('Unauthorized', 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $perPage = (int) $request->get('per_page', 15);

        $query = Item::query()->orderByDesc('id');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%");
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return ApiResponse::success('Items retrieved', $query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $item = Item::find($id);
        if (!$item) {
            return ApiResponse::error('Item not found', 400);
        }

        return ApiResponse::success('Item detail', $item);
    }

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) {
            return $adminCheck;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:machine,equipment,goods,ship,other',
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

            $item = Item::create($request->all());

            DB::commit();

            return ApiResponse::success('Item created', $item);
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
            'type' => 'required|in:machine,equipment,goods,ship,other',
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

            $item->update($request->all());

            DB::commit();

            return ApiResponse::success('Item updated', $item);
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update item: '.$e->getMessage(), 500);
        }
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
}
