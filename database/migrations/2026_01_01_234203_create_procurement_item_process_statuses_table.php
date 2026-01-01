<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_item_process_statuses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('procurement_item_id')
                ->constrained('procurement_items')
                ->cascadeOnDelete();

            $table->enum('status', ['pending', 'purchase', 'production', 'completed']);
            $table->date('production_start_date')->nullable();
            $table->date('production_end_date')->nullable();
            $table->foreignId('area_id')->nullable();
            $table->bigInteger('changed_by');
            $table->text('notes')->nullable();
            $table->date('status_date');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_item_process_statuses');
    }
};
