<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Jobs\SendLoginOtpJob;
use App\Models\EmailLoginOtp;
use App\Models\LoginLog;
use App\Models\User;
use Carbon\Carbon;
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
            $ip = $request->ip();

            $user = User::where('status', 1)
                ->where(function ($q) use ($login) {
                    $q->where('email', $login)
                      ->orWhere('username', $login);
                })
                ->first();

            if (!$user || !Hash::check($password, $user->password)) {
                usleep(150000);

                return ApiResponse::error('Invalid credentials', 401);
            }

            $trustedLogin = LoginLog::where('user_id', $user->id)
                ->where('ip_address', $ip)
                ->whereNotNull('otp_verified_at')
                ->where('otp_verified_at', '>=', Carbon::now()->subDays(8))
                ->latest()
                ->first();

            if ($trustedLogin) {
                LoginLog::create([
                    'user_id' => $user->id,
                    'ip_address' => $ip,
                    'otp_verified_at' => $trustedLogin->otp_verified_at,
                ]);

                $token = JWTAuth::fromUser($user);

                return ApiResponse::success('Login success', [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 60,
                    'otp_required' => false,
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role,
                        'avatar_url' => $user->avatar_url,
                    ],
                ]);
            }

            $otp = random_int(100000, 999999);

            EmailLoginOtp::where('email', $user->email)
                ->whereNull('verified_at')
                ->delete();

            EmailLoginOtp::create([
                'email' => $user->email,
                'otp' => $otp,
                'expired_at' => Carbon::now()->addMinutes(5),
            ]);

            dispatch(new SendLoginOtpJob($user->email, $otp));

            return ApiResponse::success('OTP sent', [
                'otp_required' => true,
                'email' => $user->email,
                'expired_in' => 300,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Login failed '.$e->getMessage(), 400);
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email|max:100',
                'otp' => 'required|digits:6',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        }

        try {
            $otp = EmailLoginOtp::where('email', $request->email)
                ->where('otp', $request->otp)
                ->whereNull('verified_at')
                ->where('expired_at', '>', Carbon::now())
                ->first();

            if (!$otp) {
                return ApiResponse::error('OTP invalid or expired', 401);
            }

            $otp->update([
                'verified_at' => Carbon::now(),
            ]);

            $user = User::where('email', $request->email)
                ->where('status', 1)
                ->firstOrFail();

            LoginLog::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'otp_verified_at' => Carbon::now(),
            ]);

            $token = JWTAuth::fromUser($user);

            return ApiResponse::success('Login success', [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'otp_required' => false,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_url' => $user->avatar_url,
                ],
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('OTP verification failed '.$e->getMessage(), 400);
        }
    }

    public function refresh()
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
        } catch (\Throwable $e) {
            return ApiResponse::error('Token refresh failed', 401);
        }

        return ApiResponse::success('Token refreshed', [
            'access_token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }

    public function logout()
    {
        try {
            JWTAuth::parseToken()->invalidate();
        } catch (\Throwable $e) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return ApiResponse::success('Logout successful');
    }
}
