<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['province_name' => 'DKI Jakarta'],
            ['province_name' => 'Jawa Barat'],
            ['province_name' => 'Jawa Tengah'],
            ['province_name' => 'Jawa Timur'],
            ['province_name' => 'Sulawesi Selatan'],
            ['province_name' => 'Sulawesi Utara'],
            ['province_name' => 'Sumatera Utara'],
            ['province_name' => 'Sumatera Selatan'],
            ['province_name' => 'Kalimantan Timur'],
            ['province_name' => 'Papua'],
        ];

        foreach ($areas as $area) {
            Area::updateOrCreate(['province_name' => $area['province_name']]);
        }
    }
}
