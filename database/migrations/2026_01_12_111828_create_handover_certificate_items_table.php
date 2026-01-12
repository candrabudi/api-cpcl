<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('handover_certificate_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('handover_certificate_id');
            $table->unsignedBigInteger('procurement_item_id')->nullable();
            
            $table->string('item_name_spec')->nullable(); // Nama Barang/Merek/Spesifikasi
            $table->decimal('quantity', 15, 2)->default(0);
            $table->string('unit')->nullable(); // Satuan
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            
            $table->timestamps();
            
            $table->foreign('handover_certificate_id', 'hc_items_hc_id_foreign')
                  ->references('id')->on('handover_certificates')
                  ->onDelete('cascade');
            $table->foreign('procurement_item_id', 'hc_items_pi_id_foreign')
                  ->references('id')->on('procurement_items')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_certificate_items');
    }
};
