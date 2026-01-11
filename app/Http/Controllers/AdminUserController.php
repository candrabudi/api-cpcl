<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    private function allowedRoles()
    {
        return ['admin', 'vendor','superadmin'];
    }

    private function authorizeAccess(Request $request)
    {
        if (!in_array($request->user()->role, $this->allowedRoles())) {
            return ApiResponse::error('Forbidden', 403);
        }

        return null;
    }

    private function authorizeSuperadmin(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return ApiResponse::error('Only superadmin can perform this action', 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($resp = $this->authorizeAccess($request)) {
            return $resp;
        }

        $perPage = (int) $request->get('per_page', 10);

        $query = User::with('detail')
            ->whereIn('role', $this->allowedRoles())
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($request->filled('role') && in_array($request->role, $this->allowedRoles())) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::success('Admin users retrieved', $query->paginate($perPage));
    }

    public function show(Request $request, $id)
    {
        if ($resp = $this->authorizeAccess($request)) {
            return $resp;
        }

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid user id', 400);
        }

        $user = User::with('detail')
            ->whereIn('role', $this->allowedRoles())
            ->find($id);

        if (!$user) {
            return ApiResponse::error('Admin user not found', 404);
        }

        return ApiResponse::success('Admin user detail retrieved', $user);
    }

    public function store(Request $request)
    {
        if ($resp = $this->authorizeSuperadmin($request)) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,superadmin',
            'status' => 'nullable|integer',
            'full_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        DB::beginTransaction();

        try {
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'status' => $request->status ?? 1,
            ]);

            UserDetail::create([
                'user_id' => $user->id,
                'full_name' => $request->full_name,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to create admin user', 500);
        }

        return ApiResponse::success('Admin user created');
    }

    public function update(Request $request, $id)
    {
        if ($resp = $this->authorizeSuperadmin($request)) {
            return $resp;
        }

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid user id', 400);
        }

        $user = User::with('detail')
            ->whereIn('role', $this->allowedRoles())
            ->find($id);

        if (!$user) {
            return ApiResponse::error('Admin user not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:users,username,'.$user->id,
            'email' => 'required|email|max:100|unique:users,email,'.$user->id,
            'old_password' => 'nullable|string',
            'new_password' => 'nullable|string|min:8',
            'role' => 'required|in:admin,superadmin',
            'status' => 'nullable|integer',
            'full_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        if ($request->filled('new_password')) {
            if (!$request->filled('old_password')) {
                return ApiResponse::error('Old password is required', 422);
            }

            if (!Hash::check($request->old_password, $user->password)) {
                return ApiResponse::error('Old password is incorrect', 422);
            }
        }

        DB::beginTransaction();

        try {
            $user->update([
                'username' => $request->username,
                'email' => $request->email,
                'role' => $request->role,
                'status' => $request->status ?? $user->status,
                'password' => $request->filled('new_password')
                    ? Hash::make($request->new_password)
                    : $user->password,
            ]);

            $user->detail()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $request->full_name,
                    'phone_number' => $request->phone_number,
                    'address' => $request->address,
                ]
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to update admin user', 500);
        }

        return ApiResponse::success('Admin user updated');
    }

    public function destroy(Request $request, $id)
    {
        if ($resp = $this->authorizeSuperadmin($request)) {
            return $resp;
        }

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid user id', 400);
        }

        $user = User::whereIn('role', $this->allowedRoles())->find($id);

        if (!$user) {
            return ApiResponse::error('Admin user not found', 404);
        }

        if ($user->role === 'superadmin') {
            return ApiResponse::error('Superadmin cannot be deleted', 403);
        }

        DB::beginTransaction();

        try {
            $user->detail()->delete();
            $user->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponse::error('Failed to delete admin user', 500);
        }

        return ApiResponse::success('Admin user deleted');
    }

    public function lock($id)
    {
        $authUser = auth()->user();

        if ($authUser->role !== 'admin') {
            return ApiResponse::error('Forbidden', 403);
        }

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid user id', 400);
        }

        $user = User::whereIn('role', $this->allowedRoles())->find($id);

        if (!$user) {
            return ApiResponse::error('User not found', 404);
        }

        if ($user->role === 'superadmin') {
            return ApiResponse::error('Superadmin cannot be locked', 403);
        }

        if ($user->id === $authUser->id) {
            return ApiResponse::error('You cannot lock your own account', 403);
        }

        $user->update(['status' => 0]);

        return ApiResponse::success('User locked');
    }

    public function unlock($id)
    {
        $authUser = auth()->user();

        if ($authUser->role !== 'admin') {
            return ApiResponse::error('Forbidden', 403);
        }

        if (!is_numeric($id)) {
            return ApiResponse::error('Invalid user id', 400);
        }

        $user = User::whereIn('role', $this->allowedRoles())->find($id);

        if (!$user) {
            return ApiResponse::error('User not found', 404);
        }

        $user->update(['status' => 1]);

        return ApiResponse::success('User unlocked');
    }
}
