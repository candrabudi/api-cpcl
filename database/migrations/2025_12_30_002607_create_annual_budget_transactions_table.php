<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('annual_budget_transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('annual_budget_id');
            $table->bigInteger('procurement_item_id');
            $table->decimal('amount', 20, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_budget_transactions');
    }
};
