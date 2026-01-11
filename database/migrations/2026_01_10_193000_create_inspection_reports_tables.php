<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Table for Berita Acara (BA) Pemeriksaan
        Schema::create('inspection_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained('procurements')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('report_number')->unique();
            $table->date('inspection_date')->nullable();
            $table->string('inspector_name')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'completed', 'verified'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Table for Checklist Items in BA
        Schema::create('inspection_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_report_id')->constrained('inspection_reports')->cascadeOnDelete();
            $table->foreignId('procurement_item_id')->constrained('procurement_items');
            $table->foreignId('shipment_item_id')->nullable()->constrained('shipment_items');
            $table->integer('expected_quantity');
            $table->integer('actual_quantity')->default(0);
            $table->boolean('is_matched')->default(false);
            $table->string('condition')->nullable(); // Good, Damaged, Wrong Item
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Table for Multiple Photos
        Schema::create('inspection_report_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_report_id')->constrained('inspection_reports')->cascadeOnDelete();
            $table->string('photo_path');
            $table->string('caption')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_report_photos');
        Schema::dropIfExists('inspection_report_items');
        Schema::dropIfExists('inspection_reports');
    }
};
