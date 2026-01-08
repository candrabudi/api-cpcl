<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained('procurements')->cascadeOnDelete();
            $table->foreignId('plenary_meeting_item_id')->constrained('plenary_meeting_items');
            $table->foreignId('plenary_meeting_id')->nullable()->constrained('plenary_meetings');
            $table->integer('quantity');
            $table->decimal('unit_price', 20, 2);
            $table->decimal('total_price', 20, 2);
            $table->string('delivery_status')->default('pending');
            $table->string('process_status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_items');
    }
};
