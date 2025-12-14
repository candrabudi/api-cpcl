<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileController extends Controller
{
    protected function authUser(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        return JWTAuth::setToken($token)->authenticate();
    }

    public function show(Request $request)
    {
        try {
            $user = $this->authUser($request);
            if (!$user) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            $cacheKey = 'profile:'.$user->id;

            $data = Cache::remember($cacheKey, 60, function () use ($user) {
                $user->load('detail');

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'email_verified_at' => $user->email_verified_at,
                    'last_login_at' => $user->last_login_at,
                    'last_login_ip' => $user->last_login_ip,
                    'created_at' => $user->created_at,
                    'detail' => [
                        'full_name' => optional($user->detail)->full_name,
                        'phone_number' => optional($user->detail)->phone_number,
                        'address' => optional($user->detail)->address,
                    ],
                ];
            });
        } catch (\Throwable $e) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return ApiResponse::success('Profile retrieved successfully', $data);
    }

    public function update(Request $request)
    {
        try {
            $this->validate($request, [
                'full_name' => 'required|string|max:100',
                'phone_number' => 'nullable|string|max:20',
                'address' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        try {
            $user = $this->authUser($request);
            if (!$user) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            UserDetail::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $request->full_name,
                    'phone_number' => $request->phone_number,
                    'address' => $request->address,
                ]
            );

            Cache::forget('profile:'.$user->id);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to update profile', 500);
        }

        return ApiResponse::success('Profile updated successfully');
    }

    public function changePassword(Request $request)
    {
        try {
            $this->validate($request, [
                'current_password' => 'required',
                'new_password' => 'required|min:6|confirmed',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        try {
            $user = $this->authUser($request);
            if (!$user) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            if (!Hash::check($request->current_password, $user->password)) {
                return ApiResponse::error('Current password is incorrect', 400);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            Cache::forget('profile:'.$user->id);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to change password', 500);
        }

        return ApiResponse::success('Password changed successfully');
    }
}
