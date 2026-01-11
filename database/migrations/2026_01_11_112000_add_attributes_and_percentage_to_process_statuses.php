<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Master Attribute yang dibuat Admin (e.g., Tahap Pengecoran, Tahap Perakitan)
        if (!Schema::hasTable('production_attributes')) {
            Schema::create('production_attributes', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // Nama Atribut / Tahapan
                $table->string('slug')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Modifikasi tabel process_statuses untuk mendukung atribut dan persentase
        Schema::table('procurement_item_process_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_item_process_statuses', 'production_attribute_id')) {
                $table->foreignId('production_attribute_id')
                    ->nullable()
                    ->after('procurement_item_id');
            }
            
            if (!Schema::hasColumn('procurement_item_process_statuses', 'percentage')) {
                $table->integer('percentage')->default(0)->after('status');
            }
        });

        // 3. Tambahkan constraint secara terpisah dengan nama pendek
        Schema::table('procurement_item_process_statuses', function (Blueprint $table) {
            $table->foreign('production_attribute_id', 'pips_attr_fk')
                ->references('id')
                ->on('production_attributes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_item_process_statuses', function (Blueprint $table) {
            $table->dropForeign('pips_attr_fk');
            $table->dropColumn(['production_attribute_id', 'percentage']);
        });
        Schema::dropIfExists('production_attributes');
    }
};
