<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        try {
            return JWTAuth::setToken($token)->authenticate();
        } catch (\Throwable $e) {
            \Log::warning('JWT authentication failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            return null;
        }
    }

    public function show(Request $request)
    {
        try {
            $user = $this->authUser($request);
            if (!$user) {
                return ApiResponse::error('Unauthenticated', 401);
            }

            $cacheKey = 'profile:' . $user->id;

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

            return ApiResponse::success('Profile retrieved successfully', $data);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve profile', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return ApiResponse::error('Unauthenticated', 401);
        }
    }

    public function update(Request $request)
    {
        try {
            $this->validate($request, [
                'full_name' => 'required|string|max:100',
                'phone_number' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        $user = $this->authUser($request);
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        try {
            DB::beginTransaction();

            UserDetail::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $request->full_name,
                    'phone_number' => $request->phone_number,
                    'address' => $request->address,
                ]
            );

            Cache::forget('profile:' . $user->id);

            DB::commit();

            \Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'updated_fields' => ['full_name', 'phone_number', 'address'],
            ]);

            return ApiResponse::success('Profile updated successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to update profile', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to update profile', 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $this->validate($request, [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        $user = $this->authUser($request);
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            \Log::warning('Failed password change attempt - incorrect current password', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return ApiResponse::error('Current password is incorrect', 400);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return ApiResponse::error('New password must be different from current password', 400);
        }

        try {
            DB::beginTransaction();

            $user->password = Hash::make($request->new_password);
            $user->save();

            Cache::forget('profile:' . $user->id);

            try {
                JWTAuth::invalidate(JWTAuth::getToken());
            } catch (\Throwable $e) {
                \Log::warning('Failed to invalidate JWT token after password change', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            DB::commit();

            \Log::info('Password changed successfully', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return ApiResponse::success('Password changed successfully. Please login again with your new password.');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Failed to change password', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to change password', 500);
        }
    }
}
