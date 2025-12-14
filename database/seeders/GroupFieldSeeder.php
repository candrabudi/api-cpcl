<?php

namespace Database\Seeders;

use App\Models\GroupField;
use App\Models\GroupFieldRow;
use Illuminate\Database\Seeder;

class GroupFieldSeeder extends Seeder
{
    public function run(): void
    {
        $document = GroupField::create([
            'title' => 'CPCL Nelayan',
            'prepared_by' => 'System',
        ]);

        $order = 1;

        $a = $this->header($document, 'A. Data Nelayan', $order++);
        $this->number($document, $a, '1) Total jumlah nelayan aktif', 'orang', $order++, true);
        $this->number($document, $a, '2) Jumlah nelayan pemilik kapal', 'orang', $order++, true);
        $this->number($document, $a, '3) Jumlah nelayan ABK (non pemilik kapal)', 'orang', $order++, true);

        $b = $this->header($document, 'B. Data Kapal', $order++);
        $this->number($document, $b, '1) Jumlah kapal aktif', 'unit', $order++, true);
        $this->composite($document, $b, '2) Ukuran kapal dominan', [
            ['key' => 'gt', 'label' => 'GT', 'type' => 'number'],
            ['key' => 'loa', 'label' => 'LoA', 'type' => 'number', 'unit' => 'm'],
        ], $order++, true);
        $this->select($document, $b, '3) Bahan/material kapal dominan', ['kayu', 'fiber', 'besi'], $order++, true);
        $this->text($document, $b, '4) Tempat pembuatan kapal', $order++);

        $c = $this->header($document, 'C. Data Mesin', $order++);
        $this->checkbox($document, $c, '1) Jenis Mesin', ['Tempel', 'Ketinting', 'Stasioner/Diesel'], $order++, true);
        $this->number($document, $c, '2) Daya mesin', 'PK / HP', $order++, true);
        $this->text($document, $c, '3) Merk Mesin', $order++, true);
        $this->composite($document, $c, '4) Spesifikasi tertentu lainnya', [
            [
                'key' => 'stasioner',
                'label' => 'Stasioner',
                'type' => 'radio',
                'options' => ['Tanpa Tangki', 'Dengan Tangki'],
            ],
            [
                'key' => 'tempel',
                'label' => 'Tempel',
                'type' => 'radio',
                'options' => ['Short', 'Long'],
            ],
            [
                'key' => 'ketinting',
                'label' => 'Ketinting',
                'type' => 'radio',
                'options' => ['Putaran lambat', 'Putaran tinggi'],
            ],
        ], $order++);
        $this->text($document, $c, '5) Umur mesin', $order++);

        $d = $this->header($document, 'D. Data Jenis dan Spesifikasi API', $order++);

        $gillnet = $this->composite($document, $d, '1) Gillnet (untuk 1 pis jaring)', [
            ['key' => 'pis_jaring', 'label' => 'Jumlah pis jaring', 'type' => 'number'],
            ['key' => 'target', 'label' => 'Target tangkapan', 'type' => 'text'],
        ], $order++, true);

        $webbing = $this->header($document, 'a) Webbing', $order++, $gillnet);

        $this->select($document, $webbing, '(1) bahan', [
            'Monofilamen',
            'Multifilamen',
            'Multimonofilamen',
        ], $order++, true);

        $this->number($document, $webbing, '(2) mesh size', 'inci', $order++, true);

        $this->composite($document, $webbing, '(3) mesh depth (MD)', [
            ['key' => 'jumlah_mata', 'label' => 'Jumlah mata jaring ke bawah', 'type' => 'number'],
            ['key' => 'dipotong', 'label' => 'Dipotong menjadi', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['dipotong', 'tidak dipotong']],
        ], $order++, true);

        $this->number($document, $webbing, '(4) panjang', 'yard', $order++, true);
        $this->number($document, $webbing, '(5) diameter benang', 'mm', $order++, true);
        $this->text($document, $webbing, '(6) warna', $order++);
        $this->select($document, $webbing, '(7) arah pintalan', [
            'yoko (mata tegak)',
            'tate (mata menyamping)',
        ], $order++, true);

        $taliGillnet = $this->header($document, 'b) Tali Temali', $order++, $gillnet);

        $this->text($document, $taliGillnet, '(1) tali ris atas', $order++);
        $this->text($document, $taliGillnet, '(2) tali pelampung', $order++);
        $this->text($document, $taliGillnet, '(3) tali ris bawah', $order++);
        $this->text($document, $taliGillnet, '(4) tali pemberat', $order++);

        $pelampungGillnet = $this->header($document, 'c) Pelampung', $order++, $gillnet);

        $this->select($document, $pelampungGillnet, '(1) bahan', ['PVC', 'karet'], $order++, true);
        $this->text($document, $pelampungGillnet, '(2) kode (jika ada)', $order++);
        $this->number($document, $pelampungGillnet, '(3) jumlah', 'buah', $order++, true);

        $pemberatGillnet = $this->header($document, 'd) Pemberat', $order++, $gillnet);

        $this->select($document, $pemberatGillnet, '(1) bahan', ['timah', 'tanah liat', 'besi', 'semen', 'batu'], $order++, true);
        $this->select($document, $pemberatGillnet, '(2) bentuk', ['silinder', 'oval', 'bulat', 'lembaran'], $order++, true);
        $this->number($document, $pemberatGillnet, '(3) jumlah', 'buah', $order++, true);
        $this->number($document, $pemberatGillnet, '(4) berat total pemberat per pis', 'kg', $order++, true);

        $trammel = $this->composite($document, $d, '2) Trammel net (untuk 1 pis jaring)', [
            ['key' => 'pis_jaring', 'label' => 'Jumlah pis jaring', 'type' => 'number'],
            ['key' => 'target', 'label' => 'Target tangkapan', 'type' => 'text'],
        ], $order++, true);

        $webbingTrammel = $this->header($document, 'a) Webbing (jaring)', $order++, $trammel);

        $inner = $this->header($document, '1) Inner', $order++, $webbingTrammel);

        $this->composite($document, $inner, '(a) selvedge atas (jika ada)', [
            ['key' => 'bahan', 'label' => 'Bahan', 'type' => 'text'],
            ['key' => 'mesh_size', 'label' => 'Mesh size', 'type' => 'number', 'unit' => 'inch'],
            ['key' => 'md', 'label' => 'MD', 'type' => 'number'],
            ['key' => 'panjang', 'label' => 'Panjang', 'type' => 'number', 'unit' => 'yard'],
            ['key' => 'diameter', 'label' => 'Diameter benang', 'type' => 'number', 'unit' => 'mm'],
        ], $order++);

        $this->composite($document, $inner, '(b) webbing (jaring)', [
            ['key' => 'bahan', 'label' => 'Bahan', 'type' => 'text'],
            ['key' => 'mesh_size', 'label' => 'Mesh size', 'type' => 'number', 'unit' => 'inch'],
            ['key' => 'md', 'label' => 'Mesh Depth', 'type' => 'number'],
            ['key' => 'panjang', 'label' => 'Panjang', 'type' => 'number', 'unit' => 'yard'],
            ['key' => 'diameter', 'label' => 'Diameter benang', 'type' => 'number', 'unit' => 'mm'],
        ], $order++, true);

        $this->composite($document, $inner, '(c) selvedge bawah (jika ada)', [
            ['key' => 'bahan', 'label' => 'Bahan', 'type' => 'text'],
            ['key' => 'mesh_size', 'label' => 'Mesh size', 'type' => 'number', 'unit' => 'inch'],
            ['key' => 'md', 'label' => 'Mesh Depth', 'type' => 'number'],
            ['key' => 'panjang', 'label' => 'Panjang', 'type' => 'number', 'unit' => 'yard'],
            ['key' => 'diameter', 'label' => 'Diameter benang', 'type' => 'number', 'unit' => 'mm'],
        ], $order++);

        $outer = $this->header($document, '2) Outer', $order++, $webbingTrammel);

        $this->composite($document, $outer, 'Outer webbing', [
            ['key' => 'bahan', 'label' => 'Bahan', 'type' => 'text'],
            ['key' => 'mesh_size', 'label' => 'Mesh size', 'type' => 'number', 'unit' => 'inch'],
            ['key' => 'md', 'label' => 'Mesh Depth', 'type' => 'number'],
            ['key' => 'panjang', 'label' => 'Panjang', 'type' => 'number', 'unit' => 'yard'],
            ['key' => 'diameter', 'label' => 'Diameter benang', 'type' => 'number', 'unit' => 'mm'],
        ], $order++, true);

        $this->header($document, 'b) Tali Temali', $order++, $trammel);

        $taliGillnet2 = $this->header($document, 'b) Tali Temali', $order++, $gillnet);
        $this->text($document, $taliGillnet2, '1) tali ris atas', $order++);
        $this->text($document, $taliGillnet2, '2) tali pelampung', $order++);
        $this->text($document, $taliGillnet2, '3) tali ris bawah', $order++);
        $this->text($document, $taliGillnet2, '4) tali pemberat', $order++);

        $pelampung2 = $this->header($document, 'c) Pelampung', $order++, $gillnet);
        $this->select($document, $pelampung2, '1) bahan', ['PVC', 'karet'], $order++, true);
        $this->text($document, $pelampung2, '2) kode (jika ada)', $order++);
        $this->number($document, $pelampung2, '3) jumlah', 'buah', $order++, true);

        $pemberat2 = $this->header($document, 'd) Pemberat', $order++, $gillnet);
        $this->select($document, $pemberat2, '1) bahan', ['timah', 'tanah liat', 'besi', 'semen', 'batu'], $order++, true);
        $this->select($document, $pemberat2, '2) bentuk', ['silinder', 'oval', 'bulat', 'lembaran'], $order++, true);
        $this->number($document, $pemberat2, '3) jumlah pemberat per pis', 'buah', $order++, true);
        $this->number($document, $pemberat2, '4) berat total pemberat per pis', 'kg', $order++, true);

        $bubu = $this->composite($document, $d, '3) Bubu', [
            ['key' => 'unit', 'label' => 'Jumlah unit bubu', 'type' => 'number'],
            ['key' => 'target', 'label' => 'Target tangkapan', 'type' => 'text'],
        ], $order++, true);

        $this->select($document, $bubu, 'a) Bentuk', ['kotak', 'oval', 'bulat'], $order++, true);

        $this->composite($document, $bubu, 'b) Dimensi (P x L x T)', [
            ['key' => 'p', 'label' => 'Panjang', 'type' => 'number', 'unit' => 'cm'],
            ['key' => 'l', 'label' => 'Lebar', 'type' => 'number', 'unit' => 'cm'],
            ['key' => 't', 'label' => 'Tinggi', 'type' => 'number', 'unit' => 'cm'],
        ], $order++, true);

        $this->select($document, $bubu, 'c) Bahan rangka', ['besi', 'kayu', 'bambu'], $order++, true);
        $this->select($document, $bubu, 'd) Bahan jaring', ['PE', 'PA'], $order++, true);

        $pancing = $this->composite($document, $d, '4) Pancing (rawai/handline)', [
            ['key' => 'mata', 'label' => 'Jumlah mata pancing', 'type' => 'number'],
            ['key' => 'target', 'label' => 'Target tangkapan', 'type' => 'text'],
        ], $order++, true);

        $mata = $this->header($document, 'a) mata pancing', $order++, $pancing);
        $this->select($document, $mata, '(1) tipe mata pancing', ['tuna', 'non tuna'], $order++, true);
        $this->text($document, $mata, '(2) nomor mata pancing', $order++);
        $this->number($document, $mata, '(3) jumlah dalam 1 rangkaian', 'buah', $order++, true);

        $taliPancing = $this->header($document, 'b) Tali Temali', $order++, $pancing);
        $this->text($document, $taliPancing, '(1) Tali utama', $order++, true);
        $this->text($document, $taliPancing, '(2) Tali cabang (jika ada)', $order++);

        $pemberatPancing = $this->header($document, 'c) Pemberat', $order++, $pancing);
        $this->select($document, $pemberatPancing, '(1) bahan', ['timah', 'tanah liat', 'besi', 'semen', 'batu'], $order++, true);
        $this->select($document, $pemberatPancing, '(2) bentuk', ['silinder', 'oval', 'bulat', 'lembaran'], $order++, true);
        $this->number($document, $pemberatPancing, '(3) jumlah pemberat per 1 rangkaian', 'buah', $order++, true);
        $this->number($document, $pemberatPancing, '(4) berat total pemberat per 1 rangkaian', 'kg', $order++, true);

        $penggulung = $this->header($document, 'd) Penggulung', $order++, $pancing);
        $this->select($document, $penggulung, '1) bahan', ['plastik', 'kayu', 'besi'], $order++, true);

        $this->composite($document, $penggulung, '2) Diameter', [
            ['key' => 'luar', 'label' => 'Ø luar', 'type' => 'number', 'unit' => 'mm'],
            ['key' => 'dalam', 'label' => 'Ø dalam', 'type' => 'number', 'unit' => 'mm'],
            ['key' => 'lebar_luar', 'label' => 'Ø lebar luar', 'type' => 'number', 'unit' => 'mm'],
            ['key' => 'lebar_dalam', 'label' => 'Ø lebar dalam', 'type' => 'number', 'unit' => 'mm'],
        ], $order++, true);

        $this->text($document, $d, 'Istilah dan definisi lokal API/Nama lokal API', $order++);
    }

    protected function header($document, $label, $order, $parent = null)
    {
        return GroupFieldRow::create([
            'group_field_id' => $document->id,
            'parent_id' => $parent?->id,
            'label' => $label,
            'row_type' => 'header',
            'order_no' => $order,
            'is_required' => false,
        ]);
    }

    protected function text($document, $parent, $label, $order, $required = false)
    {
        GroupFieldRow::create([
            'group_field_id' => $document->id,
            'parent_id' => $parent->id,
            'label' => $label,
            'row_type' => 'text',
            'order_no' => $order,
            'is_required' => $required,
        ]);
    }

    protected function number($document, $parent, $label, $unit, $order, $required = false)
    {
        GroupFieldRow::create([
            'group_field_id' => $document->id,
            'parent_id' => $parent->id,
            'label' => $label,
            'row_type' => 'number',
            'order_no' => $order,
            'is_required' => $required,
            'meta' => ['unit' => $unit],
        ]);
    }

    protected function select($document, $parent, $label, array $options, $order, $required = false)
    {
        GroupFieldRow::create([
            'group_field_id' => $document->id,
            'parent_id' => $parent->id,
            'label' => $label,
            'row_type' => 'select',
            'order_no' => $order,
            'is_required' => $required,
            'meta' => ['options' => $options],
        ]);
    }

    protected function checkbox($document, $parent, $label, array $options, $order, $required = false)
    {
        GroupFieldRow::create([
            'group_field_id' => $document->id,
            'parent_id' => $parent->id,
            'label' => $label,
            'row_type' => 'checkbox',
            'order_no' => $order,
            'is_required' => $required,
            'meta' => ['options' => $options],
        ]);
    }

    protected function composite($document, $parent, $label, array $fields, $order, $required = false)
    {
        return GroupFieldRow::create([
            'group_field_id' => $document->id,
            'parent_id' => $parent->id,
            'label' => $label,
            'row_type' => 'composite',
            'order_no' => $order,
            'is_required' => $required,
            'meta' => ['fields' => $fields],
        ]);
    }
}
