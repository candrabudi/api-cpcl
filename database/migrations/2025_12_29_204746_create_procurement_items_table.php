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
        Schema::create('procurement_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('procurement_id')
                ->constrained('procurements')
                ->cascadeOnDelete();

            $table->foreignId('plenary_meeting_item_id')
                ->constrained('plenary_meeting_items')
                ->restrictOnDelete();

            $table->foreignId('vendor_id')
                ->constrained('vendors')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 18, 2);
            $table->decimal('total_price', 18, 2);

            $table->string('delivery_status')->default('pending');

            $table->date('estimated_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->date('received_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_items');
    }
};
