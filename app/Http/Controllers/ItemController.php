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
use Carbon\Carbon;

class ItemController extends Controller
{
    private function checkAdmin($user): ?object
    {
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            \Log::warning('Unauthorized item access attempt', [
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
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $currentYear = Carbon::now()->year;

            $query = Item::with(['type.budgets' => function($q) use ($currentYear) {
                $q->where('year', $currentYear);
            }])->orderByDesc('id');

            if ($request->get('filter') === 'archived') {
                $query->onlyTrashed();
            } elseif ($request->get('show_archived') === 'true') {
                $query->withTrashed();
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where('name', 'like', "%{$search}%");
            }

            if ($request->filled('process_type')) {
                $query->where('process_type', $request->process_type);
            }

            if ($request->filled('item_type_id') && is_numeric($request->item_type_id)) {
                $query->where('item_type_id', $request->item_type_id);
            }

            $items = $query->paginate($perPage);

            $items->getCollection()->transform(function($item) {
                $type = $item->type;
                if ($type) {
                    $budget = $type->budgets->first();
                    $item->item_type_name = $type->name;
                    $item->remaining_budget = $budget ? (float)($budget->amount - $budget->used_amount) : 0;
                    
                    // Safely remove the relation to keep response clean
                    $type->unsetRelation('budgets');
                } else {
                    $item->item_type_name = null;
                    $item->remaining_budget = 0;
                }
                
                return $item;
            });

            return ApiResponse::success('Items retrieved', $items);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve items', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return ApiResponse::error('Failed to retrieve items', 500);
        }
    }

    public function show(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item ID', 400);
        }

        try {
            $item = Item::withTrashed()->with(['type', 'creator'])->find($id);
            if (!$item) {
                return ApiResponse::error('Item not found', 404);
            }

            return ApiResponse::success('Item detail', $item);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve item detail', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve item detail', 500);
        }
    }

    public function store(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'item_type_id' => 'required|exists:item_types,id',
            'description' => 'nullable|string|max:1000',
            'unit' => 'nullable|string|max:50',
            'process_type' => 'required|in:purchase,production',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $item = Item::create([
                'name' => trim($request->name),
                'item_type_id' => $request->item_type_id,
                'description' => $request->description,
                'unit' => trim($request->unit),
                'process_type' => $request->process_type,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            \Log::info('Item created successfully', [
                'item_id' => $item->id,
                'name' => $item->name,
                'created_by' => Auth::id()
            ]);

            return ApiResponse::success('Item created', $item->load('type'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to create item', [
                'name' => $request->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponse::error('Failed to create item', 500);
        }
    }

    public function update(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item ID', 400);
        }

        $item = Item::find($id);
        if (!$item) {
            return ApiResponse::error('Item not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'item_type_id' => 'required|exists:item_types,id',
            'description' => 'nullable|string|max:1000',
            'unit' => 'nullable|string|max:50',
            'process_type' => 'required|in:purchase,production',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $oldData = $item->toArray();
            $item->update([
                'name' => trim($request->name),
                'item_type_id' => $request->item_type_id,
                'description' => $request->description,
                'unit' => trim($request->unit),
                'process_type' => $request->process_type,
            ]);

            DB::commit();

            \Log::info('Item updated successfully', [
                'item_id' => $item->id,
                'old_name' => $oldData['name'],
                'new_name' => $item->name,
                'updated_by' => Auth::id()
            ]);

            return ApiResponse::success('Item updated', $item->load('type'));
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to update item', [
                'item_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponse::error('Failed to update item', 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid item ID', 400);
        }

        $item = Item::find($id);
        if (!$item) {
            return ApiResponse::error('Item not found', 404);
        }

        try {
            DB::beginTransaction();

            $itemName = $item->name;
            $item->delete();

            DB::commit();

            \Log::info('Item deleted (soft delete)', [
                'item_id' => $id,
                'name' => $itemName,
                'deleted_by' => Auth::id()
            ]);

            return ApiResponse::success('Item deleted');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to delete item', [
                'item_id' => $id,
                'error' => $e->getMessage()
            ]);

            return ApiResponse::error('Failed to delete item', 500);
        }
    }

    public function types(Request $request)
    {
        $adminCheck = $this->checkAdmin($request->user());
        if ($adminCheck) return $adminCheck;

        try {
            $perPage = min((int) $request->get('per_page', 15), 100);
            $query = ItemType::query()->orderBy('name');

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where('name', 'like', "%{$search}%");
            }

            return ApiResponse::success('Item types retrieved', $query->paginate($perPage));
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve item types', [
                'error' => $e->getMessage()
            ]);
            return ApiResponse::error('Failed to retrieve item types', 500);
        }
    }
}
