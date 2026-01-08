<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserDetail;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Superadmin
        $superadmin = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'email' => 'bagus.candrabudi@gmail.com',
                'password' => Hash::make('Superadmin@123'),
                'role' => 'superadmin',
                'status' => 1,
                'email_verified_at' => Carbon::now(),
            ]
        );

        UserDetail::updateOrCreate(
            ['user_id' => $superadmin->id],
            [
                'full_name' => 'Super Administrator',
                'phone_number' => '081111111111',
                'address' => 'Main Center',
            ]
        );

        // 2. Admin
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'email' => 'admin.bagus@gmail.com',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'status' => 1,
                'email_verified_at' => Carbon::now(),
            ]
        );

        UserDetail::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'full_name' => 'System Administrator',
                'phone_number' => '081222222222',
                'address' => 'Head Office',
            ]
        );

        // 3. Director
        $director = User::updateOrCreate(
            ['username' => 'director'],
            [
                'email' => 'director.bagus@gmail.com',
                'password' => Hash::make('Director@123'),
                'role' => 'director',
                'status' => 1,
                'email_verified_at' => Carbon::now(),
            ]
        );

        UserDetail::updateOrCreate(
            ['user_id' => $director->id],
            [
                'full_name' => 'Project Director',
                'phone_number' => '081333333333',
                'address' => 'Executive Office',
            ]
        );

    }
}
