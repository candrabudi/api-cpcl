<?php

namespace Database\Seeders;

use App\Models\ProductionAttribute;
use Illuminate\Database\Seeder;

class ProductionAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            ['name' => 'Tahap Fabrikasi (Pemotongan Material)', 'slug' => 'fabrikasi'],
            ['name' => 'Tahap Perakitan (Assembly)', 'slug' => 'perakitan'],
            ['name' => 'Tahap Finishing (Pengecatan)', 'slug' => 'finishing'],
            ['name' => 'Tahap Quality Control (Pengecekan Akhir)', 'slug' => 'qc'],
            ['name' => 'Tahap Packaging (Pengemasan)', 'slug' => 'packaging'],
        ];

        foreach ($attributes as $attr) {
            ProductionAttribute::updateOrCreate(
                ['slug' => $attr['slug']],
                ['name' => $attr['name'], 'is_active' => true]
            );
        }
    }
}
