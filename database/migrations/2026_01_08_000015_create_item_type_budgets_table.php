<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_type_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_type_id')->constrained('item_types')->cascadeOnDelete();
            $table->year('year');
            $table->decimal('amount', 20, 2);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['item_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_type_budgets');
    }
};
