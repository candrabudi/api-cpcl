<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('annual_budgets', function (Blueprint $table) {
            $table->id();
            $table->year('budget_year')->unique();
            $table->decimal('total_budget', 20, 2)->default(0);
            $table->decimal('used_budget', 20, 2)->default(0);
            $table->decimal('remaining_budget', 20, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_budgets');
    }
};
