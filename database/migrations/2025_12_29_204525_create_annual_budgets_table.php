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
        Schema::create('annual_budgets', function (Blueprint $table) {
            $table->id();
            $table->year('budget_year')->unique();
            $table->decimal('total_budget', 20, 2);
            $table->decimal('used_budget', 20, 2)->default(0);
            $table->decimal('remaining_budget', 20, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annual_budgets');
    }
};
