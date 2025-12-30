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
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plenary_meeting_id')
                ->constrained('plenary_meetings')
                ->cascadeOnDelete();
            $table->foreignId('annual_budget_allocation_id')
                ->constrained('annual_budget_allocations')
                ->cascadeOnDelete();
            $table->string('procurement_number')->unique();
            $table->date('procurement_date');
            $table->enum('status', ['draft', 'approved', 'contracted', 'in_progress', 'completed', 'cancelled'])
                ->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
