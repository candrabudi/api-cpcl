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
        Schema::create('procurement_item_status_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('procurement_item_id')
                ->constrained('procurement_items')
                ->cascadeOnDelete();

            $table->string('old_status')->nullable();
            $table->string('new_status');

            $table->date('status_date')->nullable()->after('new_status');

            $table->foreignId('changed_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_item_status_logs');
    }
};
