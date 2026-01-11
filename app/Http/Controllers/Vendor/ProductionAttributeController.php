<?php

namespace App\Http\Controllers\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductionAttribute;
use Illuminate\Http\Request;

class ProductionAttributeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = ProductionAttribute::where('is_active', true);

            if ($request->filled('item_id')) {
                $item = \App\Models\Item::find($request->item_id);
                if (!$item) {
                    return ApiResponse::error('Item not found', 404);
                }
                
                $query->where(function($q) use ($item) {
                    $q->where('item_type_id', $item->item_type_id)
                      ->orWhereNull('item_type_id');
                });
            } elseif ($request->filled('item_type_id')) {
                $query->where(function($q) use ($request) {
                    $q->where('item_type_id', $request->item_type_id)
                      ->orWhereNull('item_type_id');
                });
            }

            $attributes = $query->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return ApiResponse::success('Production attributes retrieved', $attributes);
        } catch (\Throwable $e) {
            \Log::error('Vendor - Failed to retrieve production attributes', [
                'error' => $e->getMessage(),
                'item_id' => $request->item_id
            ]);
            return ApiResponse::error('Failed to retrieve production attributes', 500);
        }
    }
}
