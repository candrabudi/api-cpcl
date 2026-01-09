<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'vendor') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Vendor access only.',
            ], 403);
        }

        return $next($request);
    }
}
