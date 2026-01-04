<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $documentTypes = [
            ['name' => 'KTP', 'description' => 'Kartu Tanda Penduduk'],
            ['name' => 'NPWP', 'description' => 'Nomor Pokok Wajib Pajak'],
            ['name' => 'SIUP', 'description' => 'Surat Izin Usaha Perdagangan'],
            ['name' => 'Surat Izin Lainnya', 'description' => 'Dokumen izin tambahan lainnya'],
        ];

        foreach ($documentTypes as $type) {
            DocumentType::updateOrCreate(
                ['name' => $type['name']],
                ['description' => $type['description']]
            );
        }
    }
}
