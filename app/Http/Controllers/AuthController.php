<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $this->validate($request, [
                'login' => 'required|string|max:100',
                'password' => 'required|string|min:6|max:100',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        try {
            $login = (string) $request->login;
            $password = (string) $request->password;

            $user = User::where('status', 1)
                ->where(function ($q) use ($login) {
                    $q->where('email', $login)->orWhere('username', $login);
                })
                ->first();

            if (!$user || !Hash::check($password, $user->password)) {
                usleep(150000);

                return ApiResponse::error('Invalid credentials', 401);
            }

            $token = JWTAuth::fromUser($user);
        } catch (\Throwable $e) {
            return ApiResponse::error('Authentication failed '.$e->getMessage(), 400);
        }

        return ApiResponse::success('Login successful', [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::parseToken()->invalidate();
        } catch (\Throwable $e) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return ApiResponse::success('Logout successful');
    }

    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $e) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return ApiResponse::success('User profile', [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
        ]);
    }
}
