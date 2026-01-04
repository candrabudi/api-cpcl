<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        $user = $request->auth;

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access only.',
            ], 403);
        }

        return $next($request);
    }
}
