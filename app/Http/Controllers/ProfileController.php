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
    /**
     * Get authenticated user from JWT token
     * SECURITY: Validates token and returns user
     */
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

    /**
     * Show user profile
     * SECURITY: User can only see their own profile
     * PERFORMANCE: Cached for 60 seconds
     */
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

    /**
     * Update user profile details
     * TRANSACTION: Protected update operation
     * SECURITY: User can only update their own profile
     */
    public function update(Request $request)
    {
        // Validation
        try {
            $this->validate($request, [
                'full_name' => 'required|string|max:100',
                'phone_number' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        // Authentication check
        $user = $this->authUser($request);
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        // TRANSACTION: Atomic update profile + clear cache
        try {
            DB::beginTransaction();

            // Update or create user detail
            UserDetail::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $request->full_name,
                    'phone_number' => $request->phone_number,
                    'address' => $request->address,
                ]
            );

            // Clear profile cache
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

    /**
     * Change user password
     * TRANSACTION: Protected password change
     * SECURITY: Validates current password, invalidates old tokens
     */
    public function changePassword(Request $request)
    {
        // Validation
        try {
            $this->validate($request, [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        // Authentication check
        $user = $this->authUser($request);
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        // SECURITY: Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            \Log::warning('Failed password change attempt - incorrect current password', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            // SECURITY: Generic error message to prevent enumeration
            return ApiResponse::error('Current password is incorrect', 400);
        }

        // SECURITY: Check if new password is same as current (optional)
        if (Hash::check($request->new_password, $user->password)) {
            return ApiResponse::error('New password must be different from current password', 400);
        }

        // TRANSACTION: Atomic password change + token invalidation + cache clear
        try {
            DB::beginTransaction();

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            // Clear profile cache
            Cache::forget('profile:' . $user->id);

            // SECURITY: Invalidate current JWT token (force re-login)
            try {
                JWTAuth::invalidate(JWTAuth::getToken());
            } catch (\Throwable $e) {
                // Token invalidation failed, but password changed - log warning
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

            // SECURITY: Generic error message
            return ApiResponse::error('Failed to change password', 500);
        }
    }
}
