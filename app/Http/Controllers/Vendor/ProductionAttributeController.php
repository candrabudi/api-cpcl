<?php

namespace App\Http\Controllers\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductionAttribute;
use Illuminate\Http\Request;

class ProductionAttributeController extends Controller
{
    /**
     * Get list of active production attributes for mobile vendor
     */
    public function index(Request $request)
    {
        try {
            $attributes = ProductionAttribute::where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            return ApiResponse::success('Production attributes retrieved', $attributes);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to retrieve production attributes', 500);
        }
    }
}
