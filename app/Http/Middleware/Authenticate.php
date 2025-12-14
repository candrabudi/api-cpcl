<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class Authenticate
{
    public function handle($request, \Closure $next)
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            $user = JWTAuth::setToken($token)->authenticate();

            if (!$user) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            $request->attributes->set('auth_user', $user);
        } catch (\Throwable $e) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return $next($request);
    }
}
