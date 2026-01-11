<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('annual_budget_transactions', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('item_type_budget_id')->constrained('items')->nullOnDelete();
            $table->integer('quantity')->nullable()->after('item_id');
            $table->decimal('unit_price', 20, 2)->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('annual_budget_transactions', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropColumn(['item_id', 'quantity', 'unit_price']);
        });
    }
};
