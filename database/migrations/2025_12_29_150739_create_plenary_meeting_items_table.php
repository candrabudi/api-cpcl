<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plenary_meeting_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plenary_meeting_id')
                ->constrained('plenary_meetings')
                ->cascadeOnDelete();

            $table->foreignId('cooperative_id')
                ->constrained('cooperatives')
                ->restrictOnDelete();

            $table->foreignId('cpcl_document_id')
                ->nullable()
                ->constrained('cpcl_documents')
                ->nullOnDelete();

            $table->foreignId('item_id')
                ->constrained('items')
                ->restrictOnDelete();

            $table->unsignedInteger('package_quantity');
            $table->string('note')->nullable();
            $table->string('location')->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();

            $table->timestamps();

            $table->unique([
                'plenary_meeting_id',
                'cooperative_id',
                'item_id',
            ], 'plenary_unique_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plenary_meeting_items');
    }
};
