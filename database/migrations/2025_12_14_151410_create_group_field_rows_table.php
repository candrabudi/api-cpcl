<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('group_field_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_field_id')
               ->constrained('group_fields')
               ->cascadeOnDelete();

            // kolom kiri (judul / pertanyaan)
            $table->string('label');

            // hasil isian (string / number / JSON composite)
            $table->json('value')->nullable();

            // jenis baris
            $table->enum('row_type', [
                'header',       // judul (3) Bubu
                'text',         // input teks
                'number',       // input angka
                'textarea',     // teks panjang
                'select',       // dropdown
                'checkbox',     // multi pilihan
                'radio',        // single pilihan
                'composite',    // input majemuk (unit + target)
                'static',       // teks petunjuk
            ])->default('text');

            $table->boolean('is_required')->default(false);

            // nested row (a, b, c, (1), (2))
            $table->foreignId('parent_id')
                ->nullable()
                ->references('id')
                ->on('group_field_rows')
                ->nullOnDelete();

            // urutan tampilan
            $table->integer('order_no')->default(0);

            // konfigurasi dynamic (options, unit, fields)
            $table->json('meta')->nullable();

            $table->index(['group_field_id', 'parent_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_field_rows');
    }
};
