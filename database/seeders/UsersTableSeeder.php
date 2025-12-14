<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            $adminId = DB::table('users')->insertGetId([
                'username' => 'admin',
                'email' => 'admin@domain.com',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'status' => 1,
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::table('user_details')->insert([
                'user_id' => $adminId,
                'full_name' => 'System Administrator',
                'phone_number' => '081234567890',
                'address' => 'Head Office',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        });
    }
}
