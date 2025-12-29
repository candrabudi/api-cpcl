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
        Schema::create('annual_budget_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('annual_budget_id')
                ->constrained('annual_budgets')
                ->cascadeOnDelete();

            $table->string('allocation_name');
            $table->decimal('allocated_amount', 20, 2);
            $table->decimal('used_amount', 20, 2)->default(0);
            $table->decimal('remaining_amount', 20, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annual_budget_allocations');
    }
};
