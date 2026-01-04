<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = [
            [
                'username' => 'vendor_alpha',
                'email' => 'alpha@vendor.com',
                'vendor_name' => 'PT Alpha Marine',
                'npwp' => '01.234.567.8-901.000',
                'contact_person' => 'Andi Wijaya',
                'phone' => '081234567801',
                'vendor_email' => 'contact@alphamarine.com',
                'address' => 'Jakarta',
                'area_id' => 1,
            ],
            [
                'username' => 'vendor_beta',
                'email' => 'beta@vendor.com',
                'vendor_name' => 'PT Beta Shipyard',
                'npwp' => '02.234.567.8-901.000',
                'contact_person' => 'Budi Santoso',
                'phone' => '081234567802',
                'vendor_email' => 'info@betashipyard.com',
                'address' => 'Surabaya',
                'area_id' => 2,
            ],
            [
                'username' => 'vendor_gamma',
                'email' => 'gamma@vendor.com',
                'vendor_name' => 'PT Gamma Offshore',
                'npwp' => '03.234.567.8-901.000',
                'contact_person' => 'Citra Lestari',
                'phone' => '081234567803',
                'vendor_email' => 'sales@gammaoffshore.com',
                'address' => 'Batam',
                'area_id' => 3,
            ],
            [
                'username' => 'vendor_delta',
                'email' => 'delta@vendor.com',
                'vendor_name' => 'PT Delta Marine Service',
                'npwp' => '04.234.567.8-901.000',
                'contact_person' => 'Dedi Pratama',
                'phone' => '081234567804',
                'vendor_email' => 'contact@deltamarine.com',
                'address' => 'Makassar',
                'area_id' => 4,
            ],
            [
                'username' => 'vendor_epsilon',
                'email' => 'epsilon@vendor.com',
                'vendor_name' => 'PT Epsilon Logistics',
                'npwp' => '05.234.567.8-901.000',
                'contact_person' => 'Eka Putri',
                'phone' => '081234567805',
                'vendor_email' => 'info@epsilonlogistics.com',
                'address' => 'Semarang',
                'area_id' => 5,
            ],
            [
                'username' => 'vendor_zeta',
                'email' => 'zeta@vendor.com',
                'vendor_name' => 'PT Zeta Shipping',
                'npwp' => '06.234.567.8-901.000',
                'contact_person' => 'Fajar Hidayat',
                'phone' => '081234567806',
                'vendor_email' => 'sales@zetashipping.com',
                'address' => 'Balikpapan',
                'area_id' => 6,
            ],
            [
                'username' => 'vendor_eta',
                'email' => 'eta@vendor.com',
                'vendor_name' => 'PT Eta Samudera',
                'npwp' => '07.234.567.8-901.000',
                'contact_person' => 'Gilang Ramadhan',
                'phone' => '081234567807',
                'vendor_email' => 'info@etasamudera.com',
                'address' => 'Pontianak',
                'area_id' => 7,
            ],
            [
                'username' => 'vendor_theta',
                'email' => 'theta@vendor.com',
                'vendor_name' => 'PT Theta Bahari',
                'npwp' => '08.234.567.8-901.000',
                'contact_person' => 'Hendra Kurnia',
                'phone' => '081234567808',
                'vendor_email' => 'contact@thetabahari.com',
                'address' => 'Manado',
                'area_id' => 8,
            ],
            [
                'username' => 'vendor_iota',
                'email' => 'iota@vendor.com',
                'vendor_name' => 'PT Iota Nautica',
                'npwp' => '09.234.567.8-901.000',
                'contact_person' => 'Indra Saputra',
                'phone' => '081234567809',
                'vendor_email' => 'info@iotanautica.com',
                'address' => 'Kupang',
                'area_id' => 9,
            ],
            [
                'username' => 'vendor_kappa',
                'email' => 'kappa@vendor.com',
                'vendor_name' => 'PT Kappa Oceanic',
                'npwp' => '10.234.567.8-901.000',
                'contact_person' => 'Joko Susilo',
                'phone' => '081234567810',
                'vendor_email' => 'sales@kappaoceanic.com',
                'address' => 'Ambon',
                'area_id' => 10,
            ],
        ];

        foreach ($vendors as $data) {
            $userId = DB::table('users')->insertGetId([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make('password'),
                'role' => 'vendor',
                'status' => 1,
                'email_verified_at' => Carbon::now(),
                'remember_token' => Str::random(10),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::table('vendors')->insert([
                'user_id' => $userId,
                'area_id' => $data['area_id'],
                'name' => $data['vendor_name'],
                'npwp' => $data['npwp'],
                'contact_person' => $data['contact_person'],
                'phone' => $data['phone'],
                'email' => $data['vendor_email'],
                'address' => $data['address'],
                'total_paid' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
