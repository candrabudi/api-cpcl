<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('procurement_number')->unique();
            $table->date('procurement_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'processed', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('annual_budget_id')->nullable()->constrained('annual_budgets')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
